<?php

namespace App\Console\Commands;

use App\Actions\Reporting\CollectHealthReport;
use App\Jobs\SendDailyHealthReportJob;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class HealthReportsCommand extends Command
{
    protected $signature = 'reports:health {--send : Queue the daily email}';

    protected $description = 'Capture hourly project health and optionally send daily summaries';

    public function handle(): void
    {
        app(CollectHealthReport::class)->handle();

        if ($this->option('send')) {
            User::query()->whereHas('projects', fn ($query) => $query->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        SendDailyHealthReportJob::dispatch($user->id, now()->toDateString());
                    }
                });
        }
    }
}
