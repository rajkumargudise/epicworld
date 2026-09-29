<?php

use App\Console\Commands\DiscoverStories;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

    Schedule::command(DiscoverStories::class)
        ->cron("*/{$frequencyMinutes} * * * *")
        ->withoutOverlapping($lockMinutes)
        ->onFailure(fn () => Log::error('Scheduled discovery run reported failure.'));
}
