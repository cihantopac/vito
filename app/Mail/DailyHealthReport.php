<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DailyHealthReport extends Mailable
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(public array $report, public string $date) {}

    public function build(): static
    {
        return $this->subject('PurePanel · Daily health report · '.$this->date)->view('mail.daily-health-report');
    }
}
