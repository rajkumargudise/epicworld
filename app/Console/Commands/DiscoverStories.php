<?php

namespace App\Console\Commands;

use App\Services\Discovery\DiscoveryRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DiscoverStories extends Command
{
    protected $signature = 'stories:discover';

    protected $description = 'Ingest all active source feeds, match/create Stories, and qualify eligible candidates.';

    /**
     * The mutex key every invocation of this command shares, regardless
     * of whether it was triggered by the scheduler or run by hand - so
     * "only one discovery run at a time" holds no matter how it's
     * started. routes/console.php's scheduled entry also applies
     * withoutOverlapping() at the scheduler level; this lock is the
     * inner, invocation-path-independent guarantee, using the same
     * database-backed cache lock Hostinger shared hosting already has
     * (see config/cache.php) - no Redis required.
     */
    public const LOCK_KEY = 'discovery:stories:discover';

    public function handle(DiscoveryRunner $runner): int
    {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('discovery.schedule.lock_seconds', 1800));

        if (! $lock->get()) {
            $this->warn('Discovery is already running; skipping this invocation to avoid an overlapping run.');

            return self::SUCCESS;
        }

        try {
            $run = $runner->run();

            $this->info(sprintf(
                'Discovery run #%d %s: %d feed(s) processed, %d stor(y/ies) touched, %d qualified as candidates, %d failed, %d skipped.',
                $run->id,
                $run->status->value,
                $run->items_processed,
                $run->items_discovered,
                $run->metrics['stories_qualified'] ?? 0,
                $run->items_failed,
                $run->items_skipped,
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
