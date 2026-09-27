<?php

namespace App\Services\Discovery;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Models\SourceFeed;
use App\Services\Feeds\RssFeedIngestor;
use App\Services\Stories\StoryQualificationService;
use Throwable;

/**
 * Ties the existing discovery components into one end-to-end run:
 *
 *   active feed -> ingest (match/create Story, record observation,
 *   preserve feed health) -> qualify eligible discovered Stories into
 *   candidates.
 *
 * Deliberately does not create editorial jobs, call an AI provider, or
 * publish anything - this only advances Stories from discovered to
 * candidate. Safe to run repeatedly: RssFeedIngestor's story matching
 * and StoryQualificationService's status guard make every step here
 * idempotent.
 */
class DiscoveryRunner
{
    public function __construct(
        private readonly RssFeedIngestor $ingestor,
        private readonly StoryQualificationService $qualifier,
    ) {}

    public function run(): AutomationRun
    {
        $run = AutomationRun::create([
            'run_type' => 'discovery',
            'status' => AutomationRunStatus::Running,
            'started_at' => now(),
        ]);

        $feedsProcessed = 0;
        $storiesTouched = 0;
        $storiesQualified = 0;

        try {
            SourceFeed::query()
                ->where('is_active', true)
                ->each(function (SourceFeed $feed) use (&$feedsProcessed, &$storiesTouched, &$storiesQualified) {
                    $feedsProcessed++;

                    $stories = $this->ingestor->ingest($feed);
                    $storiesTouched += $stories->count();

                    foreach ($stories as $story) {
                        if ($this->qualifier->qualify($story)) {
                            $storiesQualified++;
                        }
                    }
                });

            $run->update([
                'status' => AutomationRunStatus::Completed,
                'completed_at' => now(),
                'items_processed' => $feedsProcessed,
                'items_discovered' => $storiesTouched,
                'metrics' => [
                    'stories_qualified' => $storiesQualified,
                ],
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => AutomationRunStatus::Failed,
                'completed_at' => now(),
                'items_processed' => $feedsProcessed,
                'items_discovered' => $storiesTouched,
                'error' => $exception->getMessage(),
                'metrics' => [
                    'stories_qualified' => $storiesQualified,
                ],
            ]);

            throw $exception;
        }

        return $run->refresh();
    }
}
