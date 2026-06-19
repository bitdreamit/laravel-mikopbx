<?php

namespace BitDreamIT\MikoPBX\Console;

use Illuminate\Console\Scheduling\Schedule;
use BitDreamIT\MikoPBX\Jobs\{
    SyncExtensionsJob,
    CdrDailySyncJob,
    CleanOldCallLogsJob,
    BlacklistCleanupJob,
    MikoPBXHealthAlertJob
};

/**
 * MikoPBXScheduler
 *
 * Register all MikoPBX scheduled tasks into your Laravel Scheduler.
 *
 * Usage — add to your App\Console\Kernel or routes/console.php:
 *
 *   use BitDreamIT\MikoPBX\Console\MikoPBXScheduler;
 *   MikoPBXScheduler::register($schedule);
 *
 * Or in App\Console\Kernel:
 *
 *   protected function schedule(Schedule $schedule): void
 *   {
 *       MikoPBXScheduler::register($schedule);
 *   }
 */
class MikoPBXScheduler
{
    public static function register(Schedule $schedule): void
    {
        // Sync extension online/offline status every 5 minutes
        $schedule->job(new SyncExtensionsJob())
            ->everyFiveMinutes()
            ->name('mikopbx:sync-extensions')
            ->withoutOverlapping()
            ->runInBackground();

        // Sync yesterday's CDR records every day at 00:05
        $schedule->job(new CdrDailySyncJob())
            ->dailyAt('00:05')
            ->name('mikopbx:cdr-daily-sync')
            ->withoutOverlapping();

        // Clean call logs older than 90 days — run weekly
        $schedule->job(new CleanOldCallLogsJob(keepDays: 90))
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->name('mikopbx:clean-old-logs');

        // Health check alert every 10 minutes
        $schedule->job(new MikoPBXHealthAlertJob())
            ->everyTenMinutes()
            ->name('mikopbx:health-alert')
            ->withoutOverlapping();

        // Clean expired blacklist entries — daily
        $schedule->job(new BlacklistCleanupJob())
            ->daily()
            ->at('01:00')
            ->name('mikopbx:blacklist-cleanup');
    }
}
