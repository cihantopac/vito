<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reporting\CollectHealthReport;
use App\Enums\UserRole;
use App\Jobs\SendDailyHealthReportJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class HealthReportsCommand extends Command
{
    protected $signature = 'reports:health {--send : Queue the daily email now (sent automatically at 09:00)}';

    protected $description = 'Capture hourly project health and optionally send daily summaries';

    public function handle(CollectHealthReport $collect): int
    {
        $collect->handle();

        if (! $this->option('send') && now()->hour !== 9) {
            return self::SUCCESS;
        }

        $date = now()->toDateString();

        User::query()
            ->whereHas('projects', fn ($query) => $query->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))
            ->select('id')
            ->chunkById(100, function (Collection $users) use ($date): void {
                foreach ($users as $user) {
                    SendDailyHealthReportJob::dispatch($user->id, $date);
                }
            });

        return self::SUCCESS;
    }
}
