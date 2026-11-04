<?php

namespace App\Actions\CronJob;

use App\Enums\CronjobStatus;
use App\Models\CronJob;
use App\Models\Server;
use Throwable;

class SyncCronJobs
{
    /**
     * @return array<string, string> failure messages keyed by the user whose crontab could not be synced
     */
    public function sync(Server $server): array
    {
        $users = array_values(array_filter($server->getSshUsers(), fn ($u) => is_string($u) && $u !== ''));
        $errors = [];

        foreach ($users as $user) {
            try {
                $this->syncUserCronJobs($server, $user);
            } catch (Throwable $e) {
                $errors[$user] = $e->getMessage();
            }
        }

        return $errors;
    }

    private function syncUserCronJobs(Server $server, string $user): void
    {
        $crontabOutput = $this->getUserCrontab($server, $user);

        $vitoCronJobs = $server->cronJobs()
            ->where('user', $user)
            ->with('site')
            ->get();

        if ($user !== 'root') {
            foreach ($vitoCronJobs as $cronJob) {
                $cleanCommand = CronJob::unwrapAll($cronJob->command);

                if ($cleanCommand !== $cronJob->command) {
                    $cronJob->update(['command' => $cleanCommand]);
                }
            }
        }

        $serverLevelCronJobs = $vitoCronJobs->where('site_id', null);

        $lines = explode("\n", $crontabOutput);
        $serverCronJobs = [];
        $foundCronJobs = [];
        $activeLines = [];
        $unmanagedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $rawLine = $line;
            $isCommented = str_starts_with($line, '#');

            if ($isCommented) {
                $line = ltrim($line, '#');
                $line = trim($line);
            }

            $parts = preg_split('/\s+/', $line, 6);
            if (count($parts) < 6) {
                $unmanagedLines[] = $rawLine;

                continue;
            }

            $isValidCronFormat = true;
            for ($i = 0; $i < 5; $i++) {
                if (! preg_match('/^[\d\*\-\/,]+$/', $parts[$i])) {
                    $isValidCronFormat = false;
                    break;
                }
            }

            if (! $isValidCronFormat) {
                $unmanagedLines[] = $rawLine;

                continue;
            }

            $frequency = $this->normalizeFrequency(implode(' ', array_slice($parts, 0, 5)));
            $command = $this->normalizeCommand($user === 'root' ? $parts[5] : CronJob::unwrapAll($parts[5]));

            if (! $isCommented) {
                $activeLines[] = $this->normalizeCommand($line);
            }

            $serverCronJobs[] = [
                'frequency' => $frequency,
                'command' => $command,
                'commented' => $isCommented,
            ];

            $matchingCronJob = $vitoCronJobs->first(function ($cronJob) use ($frequency, $command) {
                return $this->normalizeFrequency($cronJob->frequency) === $frequency && $this->matchesCommand($cronJob, $command);
            });

            if ($matchingCronJob) {
                $foundCronJobs[] = $matchingCronJob->id;

                if ($matchingCronJob->site_id === null) {
                    if ($isCommented && $matchingCronJob->status === CronjobStatus::READY) {
                        $matchingCronJob->update(['status' => CronjobStatus::DISABLED]);
                    } elseif (! $isCommented && $matchingCronJob->status === CronjobStatus::DISABLED) {
                        $matchingCronJob->update(['status' => CronjobStatus::READY]);
                    }
                }
            }
        }

        foreach ($serverLevelCronJobs as $cronJob) {
            if (! in_array($cronJob->id, $foundCronJobs) && $cronJob->status === CronjobStatus::READY) {
                $cronJob->update(['status' => CronjobStatus::DISABLED]);
            }
        }

        foreach ($serverCronJobs as $cronJobData) {
            $isVitoManaged = $vitoCronJobs->contains(function ($cronJob) use ($cronJobData) {
                return $this->normalizeFrequency($cronJob->frequency) === $cronJobData['frequency'] && $this->matchesCommand($cronJob, $cronJobData['command']);
            });

            if (! $isVitoManaged) {
                $server->cronJobs()->create([
                    'site_id' => null, 
                    'user' => $user,
                    'command' => $cronJobData['command'],
                    'frequency' => $cronJobData['frequency'],
                    'status' => $cronJobData['commented'] ? CronjobStatus::DISABLED : CronjobStatus::READY,
                ]);
            }
        }

        $desiredLines = array_values(array_filter(explode("\n", CronJob::crontab($server, $user))));

        if (array_diff(array_map($this->normalizeCommand(...), $desiredLines), $activeLines) !== []) {
            $server->cron()->update($user, implode("\n", [...$unmanagedLines, ...$desiredLines]));
        }
    }

    private function normalizeFrequency(string $frequency): string
    {
        return preg_replace('/\s+/', ' ', trim($frequency));
    }

    private function normalizeCommand(string $command): string
    {
        return preg_replace('/\s+/', ' ', trim($command));
    }

    private function matchesCommand(CronJob $cronJob, string $serverCommand): bool
    {
        $serverCommand = $this->normalizeCommand(CronJob::unwrapAll($serverCommand));

        return in_array($serverCommand, [
            $this->normalizeCommand(CronJob::unwrapAll($cronJob->command)),
            $this->normalizeCommand(CronJob::unwrapAll($cronJob->executionCommand())),
            $this->normalizeCommand('cd '.escapeshellarg((string) $cronJob->site?->path).' && ('.$cronJob->command.')'),
        ], true);
    }

    private function getUserCrontab(Server $server, string $user): string
    {
        $output = $server->ssh($user)->exec("crontab -l 2>/dev/null || echo ''", 'get-user-crontab');

        return trim(str_replace('cron updated!', '', $output));
    }
}
