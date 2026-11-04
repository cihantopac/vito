<?php

namespace App\Actions\Reporting;

use App\Enums\ServerStatus;
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
        $report = $this->report($user, $date);
        if ($report === []) {
            return;
        }
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
     * @return array{}|array{projects: int, servers: int, critical: array<int, array{server: string, project: string, url: string, issues: array<int, string>}>, warning: array<int, array{server: string, project: string, url: string, issues: array<int, string>}>, updates: array{servers: int, packages: int, kernel: int, top: array<int, array{server: string, url: string, count: int}>}, clear: array<int, string>, missing: array<int, string>}
     */
    public function report(User $user, string $date): array
    {
        $end = CarbonImmutable::parse($date, config('app.timezone'))->setTime(9, 0);
        $projects = Project::query()->with('servers.latestMetric')
            ->whereHas('users', fn ($query) => $query->where('user_id', $user->id)->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))->get();
        if ($projects->isEmpty()) {
            return [];
        }
        $latest = HealthReportSnapshot::query()->whereIn('project_id', $projects->modelKeys())
            ->where('hour', '>', $end->subDay())->where('hour', '<=', $end)->orderBy('hour')->get()->keyBy('project_id');
        $base = rtrim(config('app.url'), '/');
        $report = ['projects' => $projects->count(), 'servers' => 0, 'critical' => [], 'warning' => [], 'updates' => ['servers' => 0, 'packages' => 0, 'kernel' => 0, 'top' => []], 'clear' => [], 'missing' => []];
        foreach ($projects as $project) {
            $snapshot = $latest->get($project->id);
            if (! $snapshot) {
                $report['missing'][] = $project->name;

                continue;
            }
            $levels = [];
            foreach ($snapshot->issues as $issue) {
                $type = strtok($issue['key'], ':');
                if ($type === 'updates' || str_contains($issue['key'], ':site:') || ! preg_match('#/servers/(\d+)#', $issue['url'], $match)) {
                    continue;
                }
                $label = str_replace(' (server measurement)', '', array_pad(explode(' · ', $issue['label'], 2), 2, '')[1]);
                $levels[(int) $match[1]][$type === 'metrics' ? 'warning' : 'critical'][] = $label;
            }
            $hasIssues = false;
            foreach ($project->servers as $server) {
                $report['servers']++;
                $issues = $levels[$server->id] ?? [];
                $metric = $server->latestMetric;
                $disk = $metric && $metric->disk_total > 0 ? 100 * $metric->disk_used / $metric->disk_total : 0;
                if ($disk >= 75 && $disk <= 90) {
                    $issues['warning'][] = 'Disk '.round($disk).'%';
                }
                if (isset($issues['critical']) && $server->status === ServerStatus::DISCONNECTED) {
                    unset($issues['warning']);
                }
                foreach ($issues as $level => $labels) {
                    $counts = array_count_values($labels);
                    $report[$level][] = [
                        'server' => $server->name,
                        'project' => $project->name,
                        'url' => $base.'/servers/'.$server->id,
                        'issues' => array_map(fn ($label, $count) => $count > 1 ? $label.' ×'.$count : $label, array_keys($counts), $counts),
                    ];
                    $hasIssues = true;
                }
                if ($server->updates > 0 || $server->kernel_updates > 0) {
                    $report['updates']['servers']++;
                    $report['updates']['packages'] += $server->updates;
                    $report['updates']['kernel'] += $server->kernel_updates;
                    $report['updates']['top'][] = ['server' => $server->name, 'url' => $base.'/servers/'.$server->id.'/update', 'count' => $server->updates];
                }
            }
            if (! $hasIssues) {
                $report['clear'][] = $project->name;
            }
        }
        foreach (['critical', 'warning'] as $level) {
            usort($report[$level], fn ($a, $b) => count($b['issues']) <=> count($a['issues']));
        }
        usort($report['updates']['top'], fn ($a, $b) => $b['count'] <=> $a['count']);
        $report['updates']['top'] = array_slice($report['updates']['top'], 0, 5);

        return $report;
    }
}
