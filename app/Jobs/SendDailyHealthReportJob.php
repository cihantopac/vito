<?php

namespace App\Jobs;

use App\Actions\Reporting\SendDailyHealthReports;
use App\Traits\UniqueQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDailyHealthReportJob implements ShouldQueue
{
    use Queueable;
    use UniqueQueue;

    public function __construct(public int $userId, public string $date) {}

    public function handle(): void
    {
        $this->run('daily-health:'.$this->userId.':'.$this->date, function (): void {
            app(SendDailyHealthReports::class)->handle($this->userId, $this->date);
        });
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Daily health report failed', ['user_id' => $this->userId, 'date' => $this->date, 'error' => $e->getMessage()]);
    }
}
