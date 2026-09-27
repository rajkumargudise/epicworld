<?php

namespace App\Console\Commands;

use App\Services\Discovery\DiscoveryRunner;
use Illuminate\Console\Command;

class DiscoverStories extends Command
{
    protected $signature = 'stories:discover';

    protected $description = 'Ingest all active source feeds, match/create Stories, and qualify eligible candidates.';

    public function handle(DiscoveryRunner $runner): int
    {
        $run = $runner->run();

        $this->info(sprintf(
            'Discovery run #%d %s: %d feed(s) processed, %d stor(y/ies) touched, %d qualified as candidates.',
            $run->id,
            $run->status->value,
            $run->items_processed,
            $run->items_discovered,
            $run->metrics['stories_qualified'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
