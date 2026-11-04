<?php

namespace App\Models;

use App\Enums\BackupRestoreStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestore extends AbstractModel
{
    protected $fillable = [
        'backup_id',
        'backup_file_id',
        'server_id',
        'target',
        'target_time',
        'status',
        'step',
        'message',
        'finished_at',
    ];

    protected $casts = [
        'backup_id' => 'integer',
        'backup_file_id' => 'integer',
        'server_id' => 'integer',
        'target_time' => 'datetime',
        'status' => BackupRestoreStatus::class,
        'finished_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Backup, $this>
     */
    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    /**
     * @return BelongsTo<BackupFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(BackupFile::class, 'backup_file_id');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
