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

class SendDailyHealthReports
{
    public function handle(int $userId, string $date): void
    {
        if ($date !== now()->toDateString()) {
            return;
        }
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }
        $groups = $this->groups($user, $date);
        if ($groups === []) {
            return;
        }
        $delivery = HealthReportDelivery::query()->firstOrCreate(
            ['user_id' => $userId, 'report_date' => $date], ['status' => 'pending'],
        );
        if (! HealthReportDelivery::query()->whereKey($delivery->id)->whereIn('status', ['pending', 'failed'])->update(['status' => 'sending'])) {
            return;
        }
        try {
            Mail::to($user->email)->send(new DailyHealthReport($groups, $date));
            $delivery->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * @return array<int, array{name: string, checks: int, counts: array{critical: int, warning: int, info: int}, servers: array<string, array{url: string, disk: ?int, open: array<int, array{label: string, url: string, level: string}>, resolved: array<int, string>}>}>
     */
    public function groups(User $user, string $date): array
    {
        $end = CarbonImmutable::parse($date, config('app.timezone'))->setTime(9, 0);
        $projects = Project::query()->with('servers.latestMetric')
            ->whereHas('users', fn ($query) => $query->where('user_id', $user->id)->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))->get();
        if ($projects->isEmpty()) {
            return [];
        }
        $snapshots = HealthReportSnapshot::query()->whereIn('project_id', $projects->modelKeys())
            ->where('hour', '>', $end->subDay())->where('hour', '<=', $end)->orderBy('hour')->get()->groupBy('project_id');
        $groups = [];
        foreach ($projects as $project) {
            $hours = $snapshots->get($project->id, collect());
            $latest = collect($hours->last()?->issues ?? [])->keyBy('key');
            $seen = $hours->flatMap(fn ($snapshot) => $snapshot->issues)->reject(fn ($issue) => str_contains($issue['key'], ':site:'))->keyBy('key');
            $servers = [];
            foreach ($project->servers as $server) {
                $metric = $server->latestMetric;
                $servers[$server->name] = [
                    'url' => rtrim(config('app.url'), '/').'/servers/'.$server->id,
                    'disk' => $metric && $metric->disk_total > 0 ? (int) round(100 * $metric->disk_used / $metric->disk_total) : null,
                    'open' => [],
                    'resolved' => [],
                ];
            }
            $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
            foreach ($seen as $key => $issue) {
                [$server, $label] = array_pad(explode(' · ', $issue['label'], 2), 2, '');
                $servers[$server] ??= ['url' => preg_replace('#(/servers/\d+).*#', '$1', $issue['url']), 'disk' => null, 'open' => [], 'resolved' => []];
                if (! $latest->has($key)) {
                    $servers[$server]['resolved'][] = $label;

                    continue;
                }
                $level = match (strtok($key, ':')) {
                    'updates' => 'info',
                    'metrics' => 'warning',
                    default => 'critical',
                };
                $counts[$level]++;
                $servers[$server]['open'][] = ['label' => $label, 'url' => $issue['url'], 'level' => $level];
            }
            $rank = fn (array $server): array => [
                count(array_filter($server['open'], fn ($issue) => $issue['level'] === 'critical')),
                count($server['open']),
                $server['disk'] ?? -1,
            ];
            uasort($servers, fn ($a, $b) => $rank($b) <=> $rank($a));
            $groups[] = ['name' => $project->name, 'checks' => $hours->count(), 'counts' => $counts, 'servers' => $servers];
        }

        return $groups;
    }
}
