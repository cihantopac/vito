<?php

namespace App\Actions\Reporting;

use App\Enums\ServerStatus;
use App\Enums\ServiceStatus;
use App\Enums\SiteResourceStatus;
use App\Enums\SiteStatus;
use App\Enums\WorkerStatus;
use App\Models\HealthReportSnapshot;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CollectHealthReport
{
    public function handle(): void
    {
        $capturedAt = now()->toImmutable();
        Project::query()->with(['servers.latestMetric', 'servers.services', 'servers.workers', 'servers.sites.resources'])
            ->chunkById(25,  function (Collection $projects) use ($capturedAt): void {
                foreach ($projects as $project) {
                    $issues = [];
                    foreach ($project->servers as $server) {
                        $base = '/servers/'.$server->id;
                        $add = function (string $key, string $severity, string $problem, string $hint, string $path) use (&$issues, $server): void {
                            $issues[] = [
                                'key' => $key,
                                'severity' => $severity,
                                'server' => $server->name,
                                'problem' => $problem,
                                'hint' => $hint,
                                'url' => rtrim(config('app.url'), '/').$path,
                            ];
                        };
                        if ($server->updates > 0 || $server->kernel_updates > 0) {
                            $add('updates:'.$server->id, 'info', $server->updates.' '.Str::plural('package', $server->updates).($server->kernel_updates > 0 ? ' + '.$server->kernel_updates.' kernel' : ''), $server->kernel_updates > 0 ? 'Reboot required.' : '', $base.'/update');
                        }
                        if ($server->status === ServerStatus::DISCONNECTED) {
                            $add('server:'.$server->id, 'critical', 'Server unreachable', 'SSH connection failed. Check the machine and SSH access.', $base);
                        }
                        if ($server->status === ServerStatus::INSTALLATION_FAILED) {
                            $add('server:'.$server->id, 'critical', 'Server installation failed', 'Check the server logs, then retry.', $base);
                        }
                        foreach ($server->services as $service) {
                            $stopped = $service->status === ServiceStatus::STOPPED;
                            if ($stopped || in_array($service->status, [ServiceStatus::FAILED, ServiceStatus::INSTALLATION_FAILED], true)) {
                                $hint = match (true) {
                                    $service->status === ServiceStatus::INSTALLATION_FAILED => 'Installation incomplete. Reinstall it.',
                                    $service->type === 'firewall' => 'Firewall is off, ports are exposed.',
                                    $stopped => 'Not running. Start it from Services.',
                                    default => 'Crashed. Check logs and restart.',
                                };
                                $add('service:'.$service->id, $stopped ? 'warning' : 'critical', ucfirst($service->name).' service '.strtolower($service->status->getText()), $hint, $base.'/services');
                            }
                        }
                        foreach ($server->workers as $worker) {
                            if (in_array($worker->status, [WorkerStatus::FAILED, WorkerStatus::STOPPED], true)) {
                                $failed = $worker->status === WorkerStatus::FAILED;
                                $path = $worker->site_id ? $base.'/sites/'.$worker->site_id.'/workers' : $base.'/workers';
                                $add('worker:'.$worker->id, $failed ? 'critical' : 'warning', 'Worker "'.($worker->name ?: '#'.$worker->id).'"'.(($domain = $server->sites->firstWhere('id', $worker->site_id)?->domain) ? ' on '.$domain : '').' '.($failed ? 'failed' : 'stopped'), $failed ? 'Keeps crashing. Check the worker logs.' : 'Not running. Start it if needed.', $path);
                            }
                        }
                        foreach ($server->sites as $site) {
                            if ($site->status === SiteStatus::INSTALLATION_FAILED) {
                                $add('site:'.$site->id, 'critical', $site->domain.' installation failed', 'Check the site logs, then retry.', $base.'/sites/'.$site->id);
                            }
                            foreach ($site->resources as $resource) {
                                if ($resource->status === SiteResourceStatus::FAILED) {
                                    $add('resource:'.$resource->id, 'warning', $site->domain.' '.strtolower($resource->type->getText()).' failed', 'Last change did not apply. Retry.', $base.'/sites/'.$site->id.'/resources');
                                }
                            }
                        }
                        if ($server->status === ServerStatus::DISCONNECTED) {
                            continue;
                        }
                        $metric = $server->latestMetric;
                        if (! $metric || $metric->created_at->lt($capturedAt->subMinutes(15))) {
                            $add('metrics:'.$server->id, 'warning', 'No monitoring data for 15+ minutes', 'Monitoring agent stopped reporting.', $base.'/monitoring');

                            continue;
                        }
                        foreach (['memory' => 'RAM', 'disk' => 'Disk'] as $field => $label) {
                            $total = $metric->{$field.'_total'};
                            $percent = $total > 0 ? 100 * $metric->{$field.'_used'} / $total : 0;
                            if ($percent > 90) {
                                $add($field.':'.$server->id, $percent > 95 ? 'critical' : 'warning', $label.' at '.round($percent, 1).'%', $field === 'disk' ? 'Free up space. All sites are affected.' : 'Processes may be killed. All sites are affected.', $base.'/monitoring');
                            }
                        }
                    }
                    HealthReportSnapshot::query()->firstOrCreate(
                        ['project_id' => $project->id, 'hour' => $capturedAt->startOfHour()],
                        ['issues' => $issues],
                    );
                }
            });
        HealthReportSnapshot::query()->where('hour', '<', now()->subDays(32))->delete();
    }
}
