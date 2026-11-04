<?php

namespace App\Actions\Reporting;

use App\Enums\UserRole;
use App\Mail\DailyHealthReport;
use App\Models\HealthReportDelivery;
use App\Models\HealthReportSnapshot;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendDailyHealthReports
{
    private const SEVERITY_RANK = ['critical' => 0, 'warning' => 1, 'info' => 2];

    public function handle(int $userId, string $date): void
    {
        if ($date !== now()->toDateString()) {
            return;
        }
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }
        $end = CarbonImmutable::parse($date, config('app.timezone'))->setTime(9, 0);
        $projects = Project::query()->whereHas('users', fn ($query) => $query->where('user_id', $userId)->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))->orderBy('name')->get();
        if ($projects->isEmpty()) {
            return;
        }
        $snapshots = HealthReportSnapshot::query()->whereIn('project_id', $projects->modelKeys())
            ->where('hour', '>', $end->subDay())->where('hour', '<=', $end)->orderBy('hour')->get()->groupBy('project_id');
        $report = $this->build($projects, $snapshots);
        $delivery = HealthReportDelivery::query()->firstOrCreate(
            ['user_id' => $userId, 'report_date' => $date], ['status' => 'pending'],
        );
        if (! HealthReportDelivery::query()->whereKey($delivery->id)->whereIn('status', ['pending', 'failed'])->update(['status' => 'sending'])) {
            return;
        }
        try {
            Mail::to($user->email)->send(new DailyHealthReport($report, $date));
            $delivery->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Project>  $projects
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, HealthReportSnapshot>>  $snapshots
     * @return array{open: list<array<string, mixed>>, updates: list<array<string, mixed>>, resolved: list<array<string, mixed>>, healthy: list<string>, no_data: list<string>, counts: array<string, int>}
     */
    private function build($projects, $snapshots): array
    {
        $open = [];
        $resolved = [];
        $healthy = [];
        $noData = [];
        foreach ($projects as $project) {
            $hours = $snapshots->get($project->id, collect());
            if ($hours->isEmpty()) {
                $noData[] = $project->name;

                continue;
            }
            $latest = $hours->last();
            $currentKeys = collect($latest->issues)->pluck('key')->flip();
            $seen = [];
            foreach ($hours as $snapshot) {
                foreach ($snapshot->issues as $issue) {
                    $seen[$issue['key']] = [
                        'issue' => $issue,
                        'checks' => ($seen[$issue['key']]['checks'] ?? 0) + 1,
                        'first' => $seen[$issue['key']]['first'] ?? $snapshot->hour,
                        'last' => $snapshot->hour,
                    ];
                }
            }
            $projectOpen = 0;
            foreach ($seen as $key => $entry) {
                if (str_contains($key, ':site:')) {
                    continue;
                }
                $issue = $entry['issue'];
                $item = [
                    'project' => $project->name,
                    'severity' => $issue['severity'] ?? match (Str::before($key, ':')) {
                        'updates' => 'info',
                        'server' => 'critical',
                        default => 'warning',
                    },
                    'server' => $issue['server'] ?? Str::before($issue['label'] ?? '', ' · '),
                    'problem' => $issue['problem'] ?? Str::after($issue['label'] ?? '', ' · '),
                    'hint' => $issue['hint'] ?? null,
                    'url' => $issue['url'],
                    'since' => $entry['first'],
                    'last' => $entry['last'],
                    'checks' => $entry['checks'],
                    'total_checks' => $hours->count(),
                ];
                if (! $currentKeys->has($key)) {
                    $resolved[] = $item;
                } else {
                    $open[] = $item;
                    $projectOpen += $item['severity'] === 'info' ? 0 : 1;
                }
            }
            if ($projectOpen === 0) {
                $healthy[] = $project->name;
            }
        }
        $sort = fn (array $a, array $b): int => [self::SEVERITY_RANK[$a['severity']] ?? 1, $a['project'], $a['server']] <=> [self::SEVERITY_RANK[$b['severity']] ?? 1, $b['project'], $b['server']];
        usort($open, $sort);
        usort($resolved, $sort);
        $updates = array_values(array_filter($open, fn (array $item): bool => $item['severity'] === 'info'));
        $open = array_values(array_filter($open, fn (array $item): bool => $item['severity'] !== 'info'));
        $counts = ['critical' => 0, 'warning' => 0, 'info' => count($updates)];
        foreach ($open as $item) {
            $counts[$item['severity']] = ($counts[$item['severity']] ?? 0) + 1;
        }

        return [
            'open' => $open,
            'updates' => $updates,
            'resolved' => collect($resolved)->where('severity', '!==', 'info')->values()->all(),
            'healthy' => $healthy,
            'no_data' => $noData,
            'counts' => $counts,
        ];
    }
}
