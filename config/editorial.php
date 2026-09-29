<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled Editorial Job Processing
    |--------------------------------------------------------------------------
    |
    | Controls the Laravel Scheduler entry registered in
    | routes/console.php for `editorial:process` - the command that
    | calls AiArticleGenerator for pending EditorialJobs. Mirrors
    | config/discovery.php's shape and reasoning exactly: Hostinger
    | (and most shared hosting) gives you exactly one cron entry -
    | `* * * * * php artisan schedule:run` - so cadence is controlled
    | here, not in the hosting control panel.
    |
    | "enabled" lets an environment without the cron entry set up yet
    | turn the schedule registration off entirely without deleting it.
    |
    | "frequency_minutes" becomes a step-syntax cron minute expression
    | (every N minutes), so it must be between 1 and 59 to be a valid
    | minute-field step.
    |
    | "lock_seconds" bounds both the scheduler's own withoutOverlapping
    | window and editorial:process's own cache lock (see
    | App\Console\Commands\ProcessEditorialJobs) - the safety net if a
    | run hangs well past its own schedule.
    |
    | "batch_limit" bounds how many pending jobs a single scheduled
    | invocation will process - each job makes a real AI provider
    | call, so this keeps one invocation from running for an unbounded
    | amount of time.
    |
    */

    'schedule' => [
        'enabled' => (bool) env('EDITORIAL_SCHEDULE_ENABLED', true),
        'frequency_minutes' => (int) env('EDITORIAL_SCHEDULE_FREQUENCY_MINUTES', 10),
        'lock_seconds' => (int) env('EDITORIAL_LOCK_SECONDS', 1800),
        'batch_limit' => (int) env('EDITORIAL_SCHEDULE_BATCH_LIMIT', 5),
    ],

];
