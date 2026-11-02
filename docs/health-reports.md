# Daily project health reports

Deploy the migration with the usual deployment procedure. The existing Laravel scheduler and queue worker must run. No additional daemon or API endpoint is needed. Configure the application's mail transport and `APP_URL` before enabling production delivery.

`reports:health` captures hourly observations. `reports:health --send` captures an observation and queues one report per authorized user. The scheduler runs delivery at 09:00 in `config('app.timezone')` (currently UTC). The email states this timezone explicitly.

Recipients must be project owners or admins at delivery time. A user receives a single email containing only their authorized projects, covering the previous 24 hours up to 09:00. Regular project members receive no report. Invitations without an existing user do not receive reports.

Snapshots cover package/kernel updates, disconnected servers, failed/stopped services and workers, failed site installations/resources, stale monitoring, and server RAM/disk strictly above 90%. Sites sharing an affected server are listed as affected sites; percentages are not site-specific measurements. Hourly sampling cannot detect every incident between observations. Missing observations are distinguished from healthy observations. Snapshots are retained for 32 days.

A database unique constraint and atomic state transition limit delivery attempts to one per user per report date, even across concurrent jobs and process restarts. Old queued reports are discarded after their report date. A failed or interrupted transport attempt is not retried that day, because the transport may already have accepted the message. Failed delivery rows remain available for investigation. No application can guarantee final inbox delivery from SMTP acceptance alone.

Run `php tests/health-report-smoke.php` for isolated, in-memory checks. This does not migrate or send email against the configured application database or mail transport.
