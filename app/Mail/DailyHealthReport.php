<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DailyHealthReport extends Mailable
{
    /**
     * @param  array{open: list<array<string, mixed>>, updates: list<array<string, mixed>>, resolved: list<array<string, mixed>>, healthy: list<string>, no_data: list<string>, counts: array<string, int>}  $report
     */
    public function __construct(public array $report, public string $date) {}

    public function build(): static
    {
        $counts = $this->report['counts'];
        $status = match (true) {
            $counts['critical'] > 0 => $counts['critical'].' critical'.($counts['warning'] > 0 ? ', '.$counts['warning'].' warning' : ''),
            $counts['warning'] > 0 => $counts['warning'].' warning',
            default => 'All healthy',
        };

        return $this->subject('Pure · '.$status.' · '.$this->date)->view('mail.daily-health-report');
    }
}
