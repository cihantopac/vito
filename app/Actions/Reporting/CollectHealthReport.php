<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

use App\Enums\ServerStatus;
use App\Enums\ServiceStatus;
use App\Enums\SiteResourceStatus;
use App\Enums\SiteStatus;
use App\Enums\WorkerStatus;
use App\Models\HealthReportSnapshot;
use App\Models\Project;

class CollectHealthReport
{
    public function handle(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $hour = now()->startOfHour();

        Project::query()->with(['servers.latestMetric', 'servers.services', 'servers.workers', 'servers.sites.resources'])
            ->chunkById(25, function ($projects) use ($appUrl, $hour): void {
                foreach ($projects as $project) {
                    $issues = [];
                    foreach ($project->servers as $server) {
                        $base = '/servers/'.$server->id;
                        $add = function (string $key, string $label, string $path) use (&$issues, $server, $appUrl): void {
                            $issues[] = ['key' => $key, 'label' => $server->name.' · '.$label, 'url' => $appUrl.$path];
                        };
                        if ($server->updates > 0 || $server->kernel_updates > 0) {
                            $add('updates:'.$server->id, $server->updates.' package updates, '.$server->kernel_updates.' kernel updates', $base.'/update');
                        }
                        if (in_array($server->status, [ServerStatus::DISCONNECTED, ServerStatus::INSTALLATION_FAILED], true)) {
                            $add('server:'.$server->id, $server->status->getText(), $base);
                        }
                        foreach ($server->services as $service) {
                            if (in_array($service->status, [ServiceStatus::FAILED, ServiceStatus::STOPPED, ServiceStatus::INSTALLATION_FAILED], true)) {
                                $add('service:'.$service->id, $service->name.' — '.$service->status->getText(), $base.'/services');
                            }
                        }
                        foreach ($server->workers as $worker) {
                            if (in_array($worker->status, [WorkerStatus::FAILED, WorkerStatus::STOPPED], true)) {
                                $path = $worker->site_id ? $base.'/sites/'.$worker->site_id.'/workers' : $base.'/workers';
                                $add('worker:'.$worker->id, ($worker->name ?: 'Worker #'.$worker->id).' — '.$worker->status->getText(), $path);
                            }
                        }
                        foreach ($server->sites as $site) {
                            if ($site->status === SiteStatus::INSTALLATION_FAILED) {
                                $add('site:'.$site->id, $site->domain.' — installation failed', $base.'/sites/'.$site->id);
                            }
                            foreach ($site->resources as $resource) {
                                if ($resource->status === SiteResourceStatus::FAILED) {
                                    $add('resource:'.$resource->id, $site->domain.' — '.$resource->type->getText().' failed', $base.'/sites/'.$site->id.'/resources');
                                }
                            }
                        }
                        $metric = $server->latestMetric;
                        if (! $metric || $metric->created_at->lt(now()->subMinutes(15))) {
                            $add('metrics:'.$server->id, 'Monitoring data unavailable or older than 15 minutes', $base);
                            continue;
                        }
                        foreach (['memory' => 'RAM', 'disk' => 'Disk'] as $field => $label) {
                            $total = $metric->{$field.'_total'};
                            $percent = $total > 0 ? 100 * $metric->{$field.'_used'} / $total : 0;
                            if ($percent > 90) {
                                $add($field.':'.$server->id, $label.' '.round($percent, 1).'% (server measurement)', $base);
                                foreach ($server->sites as $site) {
                                    $add($field.':site:'.$site->id, $site->domain.' — affected by server '.$label.' '.round($percent, 1).'%', $base.'/sites/'.$site->id);
                                }
                            }
                        }
                    }
                    HealthReportSnapshot::query()->firstOrCreate(
                        ['project_id' => $project->id, 'hour' => $hour],
                        ['issues' => $issues],
                    );
                }
            });
        HealthReportSnapshot::query()->where('hour', '<', now()->subDays(32))->delete();
    }
}
