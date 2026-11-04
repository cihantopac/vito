<?php

namespace App\Console\Commands;

use App\Mail\DailyHealthReport;
use App\Models\HealthReportSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class TestDailyCommand extends Command
{
    protected $signature = 'test:daily {email : Recipient of the sample daily notification}';

    protected $description = 'Send a sample daily health report email to the given address';

    public function handle(): int
    {
        $email = $this->argument('email');
        Validator::make(['email' => $email], ['email' => ['required', 'email']])->validate();

        $url = rtrim(config('app.url'), '/');
        $issues = [
            ['key' => 'sample-ram', 'label' => 'Sample server · RAM at 93%', 'url' => $url],
            ['key' => 'sample-disk', 'label' => 'Sample server · Disk at 88%', 'url' => $url],
        ];
        $hours = collect(range(3, 1))->map(fn (int $ago) => new HealthReportSnapshot([
            'hour' => now()->subHours($ago)->startOfHour(),
            'issues' => $ago === 1 ? [] : $issues,
        ]));
        $groups = [['name' => 'Sample Project', 'issues' => $issues, 'hours' => $hours]];

        Mail::to($email)->send(new DailyHealthReport($groups, now()->toDateString()));
        $this->info('Sample daily report sent to '.$email);

        return self::SUCCESS;
    }
}
