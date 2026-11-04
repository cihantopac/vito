<?php

namespace App\Actions\Reporting;

use App\Enums\UserRole;
use App\Mail\DailyHealthReport;
use App\Models\HealthReportDelivery;
use App\Models\HealthReportSnapshot;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class SendDailyHealthReports
{
    public function handle(int $userId, string $date): void
    {
        if ($date !== now()->toDateString()) {
            return;
        }
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }
        $end = CarbonImmutable::parse($date, config('app.timezone'))->setTime(9, 0);
        $projects = Project::query()->whereHas('users', fn ($query) => $query->where('user_id', $userId)->whereIn('role', [UserRole::ADMIN, UserRole::OWNER]))->get();
        if ($projects->isEmpty()) {
            return;
        }
        $snapshots = HealthReportSnapshot::query()->whereIn('project_id', $projects->modelKeys())
            ->where('hour', '>', $end->subDay())->where('hour', '<=', $end)->orderBy('hour')->get()->groupBy('project_id');
        $groups = [];
        foreach ($projects as $project) {
            $hours = $snapshots->get($project->id, collect());
            $issues = $hours->flatMap(fn ($snapshot) => $snapshot->issues)->unique('key')->values()->all();
            $groups[] = ['name' => $project->name, 'issues' => $issues, 'hours' => $hours];
        }
        $delivery = HealthReportDelivery::query()->firstOrCreate(
            ['user_id' => $userId, 'report_date' => $date], ['status' => 'pending'],
        );
        if (! HealthReportDelivery::query()->whereKey($delivery->id)->whereIn('status', ['pending', 'failed'])->update(['status' => 'sending'])) {
            return;
        }
        try {
            Mail::to($user->email)->send(new DailyHealthReport($groups, $date));
            $delivery->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed']);
            throw $e;
        }
    }
}
