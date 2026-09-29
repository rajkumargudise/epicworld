<?php

namespace App\Services\Discovery;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Models\SourceFeed;
use App\Services\Editorial\EditorialJobCreator;
use App\Services\Feeds\RssFeedIngestor;
use App\Services\Stories\StoryQualificationService;
use Illuminate\Support\Facades\Log;
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
 *
 * One feed is isolated from the rest: RssFeedIngestor already catches
 * ordinary network/parse failures itself and records them on the feed
 * (last_failure_at/last_error), never throwing for those. The per-feed
 * try/catch below exists for the other case - an unexpected error
 * while matching, qualifying, or creating a job for a story the feed
 * did return - so that failure is still counted and logged rather than
 * aborting the whole run and leaving every feed after it untouched.
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
        $feedsFailed = 0;
        $storiesTouched = 0;
        $storiesQualified = 0;
        $editorialJobsCreated = 0;

        try {
            SourceFeed::query()
                ->where('is_active', true)
                ->each(function (SourceFeed $feed) use (
                    &$feedsProcessed,
                    &$feedsFailed,
                    &$storiesTouched,
                    &$storiesQualified,
                    &$editorialJobsCreated
                ) {
                    $feedsProcessed++;

                    try {
                        $stories = $this->ingestor->ingest($feed);

                        // ingest() always clears last_failure_at on a
                        // successful attempt and always sets it fresh
                        // on a caught one, so its presence right after
                        // this call is a reliable signal that this
                        // attempt (not an earlier one) failed - without
                        // ingest() needing to change its return type.
                        if ($feed->refresh()->last_failure_at !== null) {
                            $feedsFailed++;
                        }

                        foreach ($stories as $story) {
                            if ($this->qualifier->qualify($story)) {
                                $storiesQualified++;
                            }

                            // Attempted for every touched Story, not only
                            // ones just qualified this run: a Story that
                            // was already a Candidate from an earlier run
                            // is still eligible, and createFor() is a
                            // no-op for anything that isn't (wrong
                            // status, or already has a job).
                            $job = $this->jobCreator->createFor($story);

                            if ($job !== null && $job->wasRecentlyCreated) {
                                $editorialJobsCreated++;
                            }
                        }

                        $storiesTouched += $stories->count();
                    } catch (Throwable $exception) {
                        $feedsFailed++;

                        Log::warning('Discovery: unexpected error processing a source feed.', [
                            'source_feed_id' => $feed->id,
                            'source_feed_name' => $feed->name,
                            'source_feed_url' => $feed->url,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                });

            $run->update([
                'status' => $feedsFailed === 0 ? AutomationRunStatus::Completed : AutomationRunStatus::Partial,
                'completed_at' => now(),
                'items_processed' => $feedsProcessed,
                'items_discovered' => $storiesTouched,
                'items_failed' => $feedsFailed,
                'items_skipped' => SourceFeed::query()->where('is_active', false)->count(),
                'metrics' => [
                    'stories_qualified' => $storiesQualified,
                    'editorial_jobs_created' => $editorialJobsCreated,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Discovery: run aborted unexpectedly.', [
                'exception' => $exception->getMessage(),
            ]);

            $run->update([
                'status' => AutomationRunStatus::Failed,
                'completed_at' => now(),
                'items_processed' => $feedsProcessed,
                'items_discovered' => $storiesTouched,
                'items_failed' => $feedsFailed,
                'items_skipped' => SourceFeed::query()->where('is_active', false)->count(),
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
