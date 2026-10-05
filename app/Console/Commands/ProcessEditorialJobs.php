<?php

namespace App\Console\Commands;

use App\Services\Ai\AiProviderManager;
use App\Services\Editorial\EditorialJobProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Processes pending editorial jobs (AI-assisted article drafting) via
 * EditorialJobProcessor / AiArticleGenerator. Never approves or
 * publishes anything - the furthest this command can move an Article
 * is Draft or Review; Scheduled and Published remain a distinct human
 * action through the admin CMS.
 */
class ProcessEditorialJobs extends Command
{
    protected $signature = 'editorial:process {--limit=10 : Maximum number of pending jobs to process in this invocation}';

    protected $description = 'Process pending editorial jobs one at a time via AiArticleGenerator. Never approves or publishes an Article.';

    /**
     * The mutex key every invocation of this command shares, regardless
     * of how it was triggered - mirrors DiscoverStories::LOCK_KEY.
     * routes/console.php's scheduled entry also applies
     * withoutOverlapping() at the scheduler level; this lock is the
     * inner, invocation-path-independent guarantee.
     */
    public const LOCK_KEY = 'editorial:process';

    public function handle(EditorialJobProcessor $processor): int
    {
        $limit = max(1, (int) $this->option('limit'));

        // With no API key for the selected provider every job would just
        // fail and burn its attempt. Leave the queue untouched until a
        // key is configured (Admin > Settings).
        if (! app(AiProviderManager::class)->isReady()) {
            $this->warn('The selected AI provider has no API key yet; leaving the queue untouched.');

            return self::SUCCESS;
        }

        $lock = Cache::lock(self::LOCK_KEY, (int) config('editorial.schedule.lock_seconds', 1800));

        if (! $lock->get()) {
            $this->warn('Editorial processing is already running; skipping this invocation to avoid an overlapping run.');

            return self::SUCCESS;
        }

        try {
            $run = $processor->process($limit);

            $this->info(sprintf(
                'Editorial processing run #%d %s: %d job(s) processed, %d completed, %d failed, %d skipped.',
                $run->id,
                $run->status->value,
                $run->items_processed,
                $run->metrics['jobs_completed'] ?? 0,
                $run->items_failed,
                $run->items_skipped,
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
