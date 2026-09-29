<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled Discovery
    |--------------------------------------------------------------------------
    |
    | Controls the Laravel Scheduler entry registered in
    | routes/console.php for `stories:discover`. Hostinger (and most
    | shared hosting) gives you exactly one cron entry -
    | `* * * * * php artisan schedule:run` - so the actual cadence is
    | controlled here, not in the hosting control panel.
    |
    | "enabled" lets an environment without the cron entry set up yet
    | turn the schedule registration off entirely without deleting it.
    |
    | "frequency_minutes" becomes a step-syntax cron minute expression
    | (every N minutes), so it must be between 1 and 59 to be a valid
    | minute-field step.
    |
    | "lock_seconds" bounds both the scheduler's own withoutOverlapping
    | window and stories:discover's own cache lock (see
    | App\Console\Commands\DiscoverStories) - the safety net if a run
    | hangs well past its own schedule, so a stuck run can't block
    | discovery forever.
    |
    */

    'schedule' => [
        'enabled' => (bool) env('DISCOVERY_SCHEDULE_ENABLED', true),
        'frequency_minutes' => (int) env('DISCOVERY_SCHEDULE_FREQUENCY_MINUTES', 15),
        'lock_seconds' => (int) env('DISCOVERY_LOCK_SECONDS', 1800),
    ],

];
