<?php

namespace App\Models;

use App\Enums\NetworkAddressingPool;
use App\Enums\NetworkStatus;
use App\Enums\NetworkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Network extends AbstractModel
{
    
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'status',
        'addressing_pool',
        'cidr',
        'cidr_canonical',
        'port',
        'region',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'port' => 'integer',
        'server_provider_id' => 'integer',
        'last_synced_at' => 'datetime',
        'type' => NetworkType::class,
        'status' => NetworkStatus::class,
        'addressing_pool' => NetworkAddressingPool::class,
    ];

    
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    
    /**
     * @return BelongsTo<ServerProvider, $this>
     */
    public function serverProvider(): BelongsTo
    {
        return $this->belongsTo(ServerProvider::class);
    }

    public function kind(): string
    {
        return match ($this->type) {
            NetworkType::PROVIDER => __(':provider private network', ['provider' => config('server-provider.providers.'.$this->serverProvider?->provider.'.label') ?? __('Cloud')]),
            NetworkType::WIREGUARD => __('WireGuard'),
            NetworkType::CUSTOM => __('Custom private network'),
        };
    }

    
    /**
     * @return HasMany<NetworkServer, $this>
     */
    public function servers(): HasMany
    {
        return $this->hasMany(NetworkServer::class);
    }

    
    /**
     * @return HasMany<NetworkFirewallRule, $this>
     */
    public function firewallRules(): HasMany
    {
        return $this->hasMany(NetworkFirewallRule::class);
    }

    
    /**
     * @return HasMany<ServerNetworkRule, $this>
     */
    public function serverRules(): HasMany
    {
        return $this->hasMany(ServerNetworkRule::class);
    }

    
    /**
     * @return HasMany<NetworkPeer, $this>
     */
    public function peers(): HasMany
    {
        return $this->hasMany(NetworkPeer::class);
    }

    
    /**
     * @return HasMany<ServerLog, $this>
     */
    public function serverLogs(): HasMany
    {
        return $this->hasMany(ServerLog::class);
    }
}
