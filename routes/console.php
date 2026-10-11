<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * HOSTING NOTE - every task below is a Schedule::call() closure that runs the artisan
 * command in-process via Artisan::call(). Schedule::command() would spawn a child
 * process (proc_open), which Hostinger disables, so every scheduled command silently
 * failed there ("The Process class relies on proc_open...") and the wire only
 * refreshed when a visitor happened to trigger it.
 */

/*
 * Scheduled discovery: invokes the existing stories:discover command
 * (App\Console\Commands\DiscoverStories), which runs the one real
 * discovery workflow, App\Services\Discovery\DiscoveryRunner. Nothing
 * here reimplements ingestion - this is only the cron entry point.
 *
 * withoutOverlapping() uses Laravel's cache-lock-backed scheduler
 * mutex. The default cache store is "database" (see config/cache.php
 * and database/migrations/..._create_cache_table.php's cache_locks
 * table), so this is safe on Hostinger shared hosting without Redis
 * or any other external service. DiscoverStories itself also takes
 * its own named cache lock (see that command), so the same
 * "only one discovery run at a time" guarantee holds even if the
 * command is ever invoked outside the scheduler - withoutOverlapping
 * here is the scheduler-level belt, the command's own lock is the
 * suspenders.
 */
if (config('discovery.schedule.enabled')) {
    // Clamped to a valid cron minute-field step (1-59) rather than
    // trusting the environment value outright.
    $frequencyMinutes = max(1, min(59, (int) config('discovery.schedule.frequency_minutes', 15)));
    $lockMinutes = max(1, intdiv((int) config('discovery.schedule.lock_seconds', 1800), 60));

    Schedule::call(fn () => Artisan::call('stories:discover'))
        ->name('stories-discover')
        ->cron("*/{$frequencyMinutes} * * * *")
        ->withoutOverlapping($lockMinutes)
        ->onFailure(fn () => Log::error('Scheduled discovery run reported failure.'));
}

/*
 * Live news wire: refreshes the world / news / local headlines and
 * videos every few minutes (see config/newswire.php). Items are shown
 * immediately; there is no AI or approval step for the wire.
 */
if (config('newswire.enabled')) {
    $wireMinutes = max(1, min(59, (int) config('newswire.fetch_every_minutes', 1)));

    Schedule::call(fn () => Artisan::call('news:fetch'))
        ->name('news-fetch')
        ->cron($wireMinutes === 1 ? '* * * * *' : "*/{$wireMinutes} * * * *")
        ->withoutOverlapping(10)
        ->onFailure(fn () => Log::error('Scheduled newswire fetch reported failure.'));
}

/*
 * Scheduled editorial job processing: invokes editorial:process
 * (App\Console\Commands\ProcessEditorialJobs), which calls
 * App\Services\Editorial\EditorialJobProcessor to run pending
 * EditorialJobs through the existing AiArticleGenerator, one job at a
 * time. This only ever reaches Draft or Review - it never approves or
 * publishes anything; see EditorialJobProcessor's docblock.
 *
 * Mirrors the discovery schedule entry above exactly: withoutOverlapping()
 * at the scheduler level, plus the command's own named cache lock
 * (ProcessEditorialJobs::LOCK_KEY) as the invocation-path-independent
 * guarantee, both backed by the database cache store - no Redis
 * required on Hostinger shared hosting.
 */
if (config('editorial.schedule.enabled')) {
    $frequencyMinutes = max(1, min(59, (int) config('editorial.schedule.frequency_minutes', 10)));
    $lockMinutes = max(1, intdiv((int) config('editorial.schedule.lock_seconds', 1800), 60));
    $batchLimit = max(1, (int) config('editorial.schedule.batch_limit', 5));

    Schedule::call(fn () => Artisan::call('editorial:process', ['--limit' => $batchLimit]))
        ->name('editorial-process')
        ->cron("*/{$frequencyMinutes} * * * *")
        ->withoutOverlapping($lockMinutes)
        ->onFailure(fn () => Log::error('Scheduled editorial processing run reported failure.'));
}

/*
 * Live TV: works out which news channels are streaming (and embeddable) right now,
 * so the site never shows a player that would say "something went wrong".
 */
if (config('newswire.enabled')) {
    Schedule::call(fn () => app(\App\Services\NewsWire\LiveChannelResolver::class)->refresh())
        ->name('live-channels')
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->onFailure(fn () => Log::error('Live channel refresh failed.'));

    // Writes the full in-site report for the most important fresh headlines.
    Schedule::call(fn () => Artisan::call('wire:write-reports', ['--limit' => (int) config('newswire.reports.per_run', 2)]))
        ->name('wire-write-reports')
        ->everyMinute()
        ->withoutOverlapping(15)
        ->onFailure(fn () => Log::error('Wire report writing failed.'));
}

/*
 * Daily publishing queue: at 15:00 IST the oldest approved article goes live (one per day).
 */
Schedule::call(fn () => Artisan::call('articles:publish-queue'))
    ->name('publish-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onFailure(fn () => Log::error('Publishing queue run failed.'));
