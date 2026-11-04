<?php

namespace App\Models;

use App\Enums\CommandExecutionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandExecution extends AbstractModel
{
    
    use HasFactory;

    protected $fillable = [
        'command_id',
        'cron_job_id',
        'server_id',
        'user_id',
        'server_log_id',
        'variables',
        'status',
    ];

    protected $casts = [
        'command_id' => 'integer',
        'cron_job_id' => 'integer',
        'server_id' => 'integer',
        'user_id' => 'integer',
        'server_log_id' => 'integer',
        'variables' => 'array',
        'status' => CommandExecutionStatus::class,
    ];

    
    /**
     * @return BelongsTo<Command, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    /**
     * @return BelongsTo<CronJob, $this>
     */
    public function cronJob(): BelongsTo
    {
        return $this->belongsTo(CronJob::class);
    }

    public function getContent(): string
    {
        $content = (string) ($this->command?->command ?? $this->cronJob?->command ?? '');
        foreach ((array) $this->variables as $variable => $value) {
            if (is_string($value) && ($value !== '' && $value !== '0')) {
                $content = str_replace('${'.$variable.'}', $value, $content);
            }
        }

        return $content;
    }

    
    /**
     * @return BelongsTo<ServerLog, $this>
     */
    public function serverLog(): BelongsTo
    {
        return $this->belongsTo(ServerLog::class);
    }

    
    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
