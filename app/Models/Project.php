<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends AbstractModel
{
    use HasFactory;
    use SoftDeletes;

    private array $resolvedUserRoles = [];

    protected $fillable = [
        'name',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::deleting(function (Project $project): void {
            if ($project->isSelf()) {
                throw new \RuntimeException('The Pure system project cannot be deleted.');
            }
        });
    }

    public function isSelf(): bool
    {
        return $this->name === 'Pure' || $this->servers()->where('is_self', true)->exists();
    }

    public function scopeForResource(Builder $query, User $viewer): Builder
    {
        return $query
            ->withCount(['servers', 'sites'])
            ->with(['users' => fn ($users) => $users->where('user_id', $viewer->id)]);
    }

    /**
     * @return HasMany<Server, $this>
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * @return HasManyThrough<Site, Server, $this>
     */
    public function sites(): HasManyThrough
    {
        return $this->hasManyThrough(Site::class, Server::class);
    }

    /**
     * @return HasManyThrough<Backup, Server, $this>
     */
    public function backups(): HasManyThrough
    {
        return $this->hasManyThrough(Backup::class, Server::class);
    }

    /**
     * @return HasMany<NotificationChannel, $this>
     */
    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    /**
     * @return HasMany<UserProject, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(UserProject::class, 'project_id');
    }

    /**
     * @return HasMany<SourceControl, $this>
     */
    public function sourceControls(): HasMany
    {
        return $this->hasMany(SourceControl::class);
    }

    public function hasRoles(User $user, array $roles): bool
    {
        $role = $this->role($user);

        return $role !== null && in_array($role, $roles, true);
    }

    public function role(User $user): ?UserRole
    {
        if ($this->relationLoaded('users')) {
            return $this->users->firstWhere('user_id', $user->id)?->role;
        }

        if (! array_key_exists($user->id, $this->resolvedUserRoles)) {
            $this->resolvedUserRoles[$user->id] = $this->users()
                ->where('user_id', $user->id)
                ->first()
                ?->role;
        }

        return $this->resolvedUserRoles[$user->id];
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * @return HasMany<Network, $this>
     */
    public function networks(): HasMany
    {
        return $this->hasMany(Network::class);
    }

    /**
     * @return HasMany<StorageMigration, $this>
     */
    public function storageMigrations(): HasMany
    {
        return $this->hasMany(StorageMigration::class);
    }
}
