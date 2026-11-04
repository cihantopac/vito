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
     * @return array<int, array{name: string, checks: int, critical: array<string, array<int, array{label: string, url: string}>>, warning: array<string, array<int, array{label: string, url: string, level: string}>>, disks: array<string, array{url: string, disk: ?int}>}>
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
            $issues = collect($hours->last()?->issues ?? [])->reject(fn ($issue) => str_contains($issue['key'], ':site:'));
            $critical = [];
            $warning = [];
            foreach ($issues as $issue) {
                [$server, $label] = array_pad(explode(' · ', $issue['label'], 2), 2, '');
                $level = match (strtok($issue['key'], ':')) {
                    'updates' => 'info',
                    'metrics' => 'warning',
                    default => 'critical',
                };
                if ($level === 'critical') {
                    $critical[$server][] = ['label' => $label, 'url' => $issue['url']];
                } else {
                    $warning[$server][] = ['label' => $label, 'url' => $issue['url'], 'level' => $level];
                }
            }
            uasort($critical, fn ($a, $b) => count($b) <=> count($a));
            foreach ($warning as $server => $items) {
                usort($items, fn ($a, $b) => ($b['level'] === 'warning') <=> ($a['level'] === 'warning'));
                $warning[$server] = $items;
            }
            uasort($warning, fn ($a, $b) => [$b[0]['level'] === 'warning', count($b)] <=> [$a[0]['level'] === 'warning', count($a)]);
            $disks = [];
            foreach ($project->servers as $server) {
                $metric = $server->latestMetric;
                $disks[$server->name] = [
                    'url' => rtrim(config('app.url'), '/').'/servers/'.$server->id,
                    'disk' => $metric && $metric->disk_total > 0 ? (int) round(100 * $metric->disk_used / $metric->disk_total) : null,
                ];
            }
            uasort($disks, fn ($a, $b) => ($b['disk'] ?? -1) <=> ($a['disk'] ?? -1));
            $groups[] = ['name' => $project->name, 'checks' => $hours->count(), 'critical' => $critical, 'warning' => $warning, 'disks' => $disks];
        }

        return $groups;
    }
}
