<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DailyHealthReport extends Mailable
{
    public function __construct(public array $report, public string $date) {}

    public function build(): static
    {
        return $this->subject('PurePanel · Daily health report · '.$this->date)->view('mail.daily-health-report');
    }
}
