<?php

namespace App\Models;

use App\Enums\EnvVersionSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvVersion extends AbstractModel
{
    protected $fillable = [
        'site_id',
        'user_id',
        'restored_from_id',
        'path',
        'content',
        'source',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'user_id' => 'integer',
        'restored_from_id' => 'integer',
        'content' => 'encrypted',
        'source' => EnvVersionSource::class,
    ];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
