<?php

namespace App\Console\Commands;

use App\Services\NewsWire\NewsWireFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FetchNewsWire extends Command
{
    protected $signature = 'news:fetch';

    protected $description = 'Fetch the live world / news / local headlines and videos.';

    public function handle(NewsWireFetcher $fetcher): int
    {
        if (! config('newswire.enabled')) {
            $this->warn('Newswire is disabled.');

            return self::SUCCESS;
        }

        $lock = Cache::lock(NewsWireFetcher::LOCK_KEY, 300);

        if (! $lock->get()) {
            $this->warn('Another fetch is already running.');

            return self::SUCCESS;
        }

        try {
            $stats = $fetcher->run();
        } finally {
            $lock->release();
        }

        $this->info("Fetched {$stats['feeds']} feeds ({$stats['failed']} failed): {$stats['new_items']} new items, {$stats['pruned']} pruned.");

        return self::SUCCESS;
    }
}
