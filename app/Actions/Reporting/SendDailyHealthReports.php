<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

use App\Enums\HealthReportDeliveryStatus;
use App\Enums\ServerStatus;
use App\Enums\UserRole;
use App\Mail\DailyHealthReport;
use App\Models\HealthReportDelivery;
use App\Models\HealthReportSnapshot;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyHealthReports
{
    private const int DISK_WARNING_PERCENT = 75;

    private const int DISK_CRITICAL_PERCENT = 90;

    private const int TOP_UPDATES = 5;

    public function handle(int $userId, string $date): void
    {
        if ($date !== now()->toDateString()) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            return;
        }

        $report = $this->report($user, $date);
        if ($report === []) {
            return;
        }

        $delivery = HealthReportDelivery::query()->firstOrCreate(
            ['user_id' => $userId, 'report_date' => $date],
            ['status' => HealthReportDeliveryStatus::PENDING],
        );

        $claimed = HealthReportDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', HealthReportDeliveryStatus::PENDING)
            ->update(['status' => HealthReportDeliveryStatus::SENDING]);

        if ($claimed === 0) {
            return;
        }

        try {
            Mail::to($user->email)->send(new DailyHealthReport($report, $date));
            $delivery->update(['status' => HealthReportDeliveryStatus::SENT]);
        } catch (Throwable $e) {
            $delivery->update(['status' => HealthReportDeliveryStatus::FAILED]);

            throw $e;
        }
    }

    public function report(User $user, string $date): array
    {
        $projects = Project::query()
            ->with('servers.latestMetric')
            ->whereHas('users', fn ($query) => $query->where('user_id', $user->id)->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))
            ->get();

        if ($projects->isEmpty()) {
            return [];
        }

        $snapshots = $this->latestSnapshots($projects->modelKeys(), CarbonImmutable::parse($date, config('app.timezone'))->setTime(9, 0));
        $base = rtrim((string) config('app.url'), '/');
        $report = ['projects' => $projects->count(), 'servers' => 0, 'critical' => [], 'warning' => [], 'updates' => ['servers' => 0, 'packages' => 0, 'kernel' => 0, 'top' => []], 'clear' => [], 'missing' => []];

        foreach ($projects as $project) {
            $snapshot = $snapshots[$project->id] ?? null;
            if (! $snapshot instanceof HealthReportSnapshot) {
                $report['missing'][] = $project->name;

                continue;
            }

            $levels = $this->issuesByServer($snapshot);
            $hasIssues = false;

            foreach ($project->servers as $server) {
                $report['servers']++;
                $url = $base.'/servers/'.$server->id;
                $down = $server->status === ServerStatus::DISCONNECTED;

                foreach ($this->serverIssues($server, $levels[$server->id] ?? [], $down) as $level => $labels) {
                    $report[$level][] = ['server' => $server->name, 'project' => $project->name, 'url' => $url, 'down' => $down, 'issues' => $this->byStatus($labels)];
                    $hasIssues = true;
                }

                if ($server->updates > 0 || $server->kernel_updates > 0) {
                    $report['updates']['servers']++;
                    $report['updates']['packages'] += (int) $server->updates;
                    $report['updates']['kernel'] += (int) $server->kernel_updates;
                    $report['updates']['top'][] = ['server' => $server->name, 'url' => $url.'/update', 'count' => (int) $server->updates];
                }
            }

            if (! $hasIssues) {
                $report['clear'][] = $project->name;
            }
        }

        foreach (['critical', 'warning'] as $level) {
            usort($report[$level], fn (array $a, array $b): int => [$b['down'], count($b['issues'], COUNT_RECURSIVE), $a['server']] <=> [$a['down'], count($a['issues'], COUNT_RECURSIVE), $b['server']]);
        }

        usort($report['updates']['top'], fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $report['updates']['top'] = array_slice($report['updates']['top'], 0, self::TOP_UPDATES);

        return $report;
    }

    private function latestSnapshots(array $projectIds, CarbonImmutable $end): array
    {
        $inWindow = fn ($query) => $query->whereIn('project_id', $projectIds)->where('hour', '>', $end->subDay())->where('hour', '<=', $end);

        return HealthReportSnapshot::query()
            ->whereIn('id', $inWindow(HealthReportSnapshot::query()->selectRaw('max(id)')->groupBy('project_id')))
            ->get()
            ->keyBy('project_id')
            ->all();
    }

    private function issuesByServer(HealthReportSnapshot $snapshot): array
    {
        $levels = [];

        foreach ($snapshot->issues as $issue) {
            $type = strtok($issue['key'], ':');
            if ($type === 'updates' || str_contains($issue['key'], ':site:') || ! preg_match('#/servers/(\d+)#', $issue['url'], $match)) {
                continue;
            }

            $label = $type === 'metrics'
                ? 'No monitoring data for 15+ min'
                : str_replace(' (server measurement)', '', explode(' · ', $issue['label'], 2)[1] ?? $issue['label']);

            $levels[(int) $match[1]][$type === 'metrics' ? 'warning' : 'critical'][] = $label;
        }

        return $levels;
    }

    private function serverIssues(Server $server, array $issues, bool $down): array
    {
        $metric = $server->latestMetric;
        $disk = $metric && $metric->disk_total > 0 ? 100 * $metric->disk_used / $metric->disk_total : 0;

        if ($disk >= self::DISK_WARNING_PERCENT && $disk <= self::DISK_CRITICAL_PERCENT) {
            $issues['warning'][] = 'Disk '.round($disk).'%';
        }

        if ($down && isset($issues['critical'])) {
            unset($issues['warning']);
        }

        return $issues;
    }

    private function byStatus(array $labels): array
    {
        $groups = [];

        foreach (array_count_values($labels) as $label => $count) {
            [$name, $status] = array_pad(explode(' — ', (string) $label, 2), 2, '');
            $name = $status === '' ? ucfirst($name) : $name;
            $groups[ucfirst(str_replace('_', ' ', $status))][] = $count > 1 ? $name.' ×'.$count : $name;
        }

        return $groups;
    }
}
