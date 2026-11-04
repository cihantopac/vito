<?php

namespace App\Actions\Overview;

use App\Enums\DeploymentStatus;
use App\Http\Resources\ProjectOverviewServerResource;
use App\Http\Resources\ProjectOverviewSiteResource;
use App\Models\Project;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class GetOverviewResources
{
    private const int LIMIT = 6;

    private User $user;

    private bool $isAdmin;

    private array $projectIds = [];

    public function handle(User $user, array $serverIds, array $siteIds, ?int $fallbackServerId = null): array
    {
        $this->user = $user;
        $this->isAdmin = $user->isAdmin();

        if (! $this->isAdmin) {
            $this->projectIds = $user->allProjects()->pluck('projects.id')->all();
        }

        $servers = $this->recent(
            $this->scopeToProjects(Server::query())
                ->with('latestMetric')
                ->withCount([
                    'backups as backups_with_health_count' => fn (Builder $query) => $query->whereNotNull('health'),
                ]),
            $serverIds,
        );
        $sites = $this->recent($this->siteQuery(), $siteIds, $fallbackServerId);

        return [
            'servers' => ProjectOverviewServerResource::collection($servers)->resolve(),
            'sites' => ProjectOverviewSiteResource::collection($sites)->resolve(),
            'projects' => $this->latestOf(
                ($this->isAdmin ? Project::query() : $this->user->allProjects())->withCount('users'),
                fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'users_count' => $project->users_count,
                    'is_current' => $project->id === $this->user->current_project_id,
                ],
            ),

        ];
    }

    private function recent(Builder $query, array $ids, ?int $fallbackServerId = null): Collection
    {
        $ids = array_slice(array_values(array_unique($ids)), 0, self::LIMIT);
        $items = $ids !== []
            ? (clone $query)
                ->whereKey($ids)
                ->get()
                ->sortBy(fn (Model $model): int => (int) array_search($model->getKey(), $ids, true))
                ->values()
            : new Collection;

        if ($items->count() < self::LIMIT) {
            $topUp = clone $query;
            if ($items->isNotEmpty()) {
                $topUp->whereNotIn('id', $items->modelKeys());
            }

            if ($fallbackServerId !== null) {
                $topUp->where('server_id', $fallbackServerId);
            }

            $items = $items->concat($topUp->latest('id')->limit(self::LIMIT - $items->count())->get())->values();
        }

        return $items;
    }

    private function latestOf(Builder $query, callable $map): array
    {
        return $query->latest('id')->take(self::LIMIT)->get()->map($map)->all();
    }

    private function scopeToProjects(Builder $query): Builder
    {
        return $query->when(! $this->isAdmin, fn ($query) => $query->whereIn('project_id', $this->projectIds));
    }

    private function scopeToServerProjects(Builder $query): Builder
    {
        return $query->when(! $this->isAdmin, fn ($query) => $query->whereHas(
            'server',
            fn (Builder $query) => $query->whereIn('project_id', $this->projectIds),
        ));
    }

    private function siteQuery(): Builder
    {
        return $this->scopeToServerProjects(Site::query())
            ->with(['server', 'hostedDomains.ssl', 'workers'])
            ->withExists([
                'deployments as has_finished_deployment' => fn (Builder $query) => $query->where('status', DeploymentStatus::FINISHED),
            ]);
    }
}
