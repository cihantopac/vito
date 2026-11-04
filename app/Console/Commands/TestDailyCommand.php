<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reporting\SendDailyHealthReports;
use App\Mail\DailyHealthReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestDailyCommand extends Command
{
    protected $signature = 'test:daily {email : Email of the user to receive their daily health report}';

    protected $description = 'Send the daily health report with real data to the given user now';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user found with this email.');

            return self::FAILURE;
        }

        $date = now()->toDateString();
        $report = app(SendDailyHealthReports::class)->report($user, $date);
        if ($report === []) {
            $this->error('This user is not an owner or admin of any project.');

            return self::FAILURE;
        }

        Mail::to($user->email)->send(new DailyHealthReport($report, $date));
        $this->info('Daily health report sent to '.$user->email);

        return self::SUCCESS;
    }
}
