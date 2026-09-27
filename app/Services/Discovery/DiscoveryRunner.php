<?php

namespace App\Services\Discovery;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Models\SourceFeed;
use App\Services\Editorial\EditorialJobCreator;
use App\Services\Feeds\RssFeedIngestor;
use App\Services\Stories\StoryQualificationService;
use Throwable;

/**
 * Ties the existing discovery components into one end-to-end run:
 *
 *   active feed -> ingest (match/create Story, record observation,
 *   preserve feed health) -> qualify eligible discovered Stories into
 *   candidates -> create the editorial job that will (later) turn each
 *   candidate into a drafted Article.
 *
 * Deliberately does not process editorial jobs, call an AI provider, or
 * publish anything - this only advances Stories from discovered to
 * candidate and gets a pending job attached. Safe to run repeatedly:
 * RssFeedIngestor's story matching, StoryQualificationService's status
 * guard, and EditorialJobCreator's duplicate check make every step here
 * idempotent.
 */
class DiscoveryRunner
{
    public function __construct(
        private readonly RssFeedIngestor $ingestor,
        private readonly StoryQualificationService $qualifier,
        private readonly EditorialJobCreator $jobCreator,
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
        $editorialJobsCreated = 0;

        try {
            SourceFeed::query()
                ->where('is_active', true)
                ->each(function (SourceFeed $feed) use (&$feedsProcessed, &$storiesTouched, &$storiesQualified, &$editorialJobsCreated) {
                    $feedsProcessed++;

                    $stories = $this->ingestor->ingest($feed);
                    $storiesTouched += $stories->count();

                    foreach ($stories as $story) {
                        if ($this->qualifier->qualify($story)) {
                            $storiesQualified++;
                        }

                        // Attempted for every touched Story, not only ones
                        // just qualified this run: a Story that was
                        // already a Candidate from an earlier run is
                        // still eligible, and createFor() is a no-op for
                        // anything that isn't (wrong status, or already
                        // has a job).
                        $job = $this->jobCreator->createFor($story);

                        if ($job !== null && $job->wasRecentlyCreated) {
                            $editorialJobsCreated++;
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
                    'editorial_jobs_created' => $editorialJobsCreated,
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
                    'editorial_jobs_created' => $editorialJobsCreated,
                ],
            ]);

            throw $exception;
        }

        return $run->refresh();
    }
}
