<?php

namespace App\Services\Editorial;

use App\Enums\AutomationRunStatus;
use App\Enums\EditorialJobStatus;
use App\Models\AutomationRun;
use App\Models\EditorialJob;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The operational bridge between EditorialJobCreator's output (pending
 * EditorialJobs) and AiArticleGenerator's already-built single-job
 * drafting entry point. Before Milestone 16, nothing ever called
 * AiArticleGenerator::generate() - a pending job simply sat there
 * forever. This is that "something" its docblock reserved for a later
 * milestone: a batch runner that calls generate() one job at a time,
 * in the same per-item-isolated, AutomationRun-tracked shape
 * DiscoveryRunner already established for the discovery side of the
 * pipeline.
 *
 * This never introduces automatic publication. generate() itself only
 * ever moves an Article as far as Draft or Review (via
 * PublicationDecision, which is the only thing that decides that) -
 * this processor calls nothing beyond generate() and does not touch
 * PublicationPolicy. Approving (Review -> Scheduled) and publishing
 * (Scheduled -> Published) remain a distinct, explicit human/admin
 * action through the existing admin.articles.approve/publish routes.
 */
class EditorialJobProcessor
{
    public const RUN_TYPE = 'editorial_processing';

    public function __construct(
        private readonly AiArticleGenerator $generator,
    ) {}

    public function process(int $limit = 10): AutomationRun
    {
        $run = AutomationRun::create([
            'run_type' => self::RUN_TYPE,
            'status' => AutomationRunStatus::Running,
            'started_at' => now(),
        ]);

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $skipped = 0;
        $stoppedReason = null;

        try {
            $jobs = EditorialJob::query()
                ->where('job_type', EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION)
                ->where('status', EditorialJobStatus::Pending)
                ->when(config('editorial.priority_source_slugs'), function ($query, array $slugs) {
                    $marks = implode(',', array_fill(0, count($slugs), '?'));

                    $query->orderByRaw(
                        "(exists (select 1 from source_story ss join sources s on s.id = ss.source_id where ss.story_id = editorial_jobs.story_id and s.slug in ({$marks}))) desc",
                        array_values($slugs),
                    );
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($jobs as $job) {
                $processed++;

                try {
                    // Re-fetched rather than reusing the row from the
                    // batch query above: something else (another
                    // invocation, an admin action) may have touched
                    // this job since that batch was read, and this is
                    // the atomic point where eligibility is decided -
                    // never held open across the AI provider call
                    // itself.
                    $fresh = EditorialJob::find($job->id);

                    if ($fresh === null || $fresh->status !== EditorialJobStatus::Pending) {
                        $skipped++;

                        continue;
                    }

                    $result = $this->generator->generate($fresh);

                    match ($result->status) {
                        EditorialJobStatus::Completed => $completed++,
                        EditorialJobStatus::Failed => $failed++,
                        // Any other outcome is one of generate()'s own
                        // no-op guards (job type / story no longer a
                        // Candidate) - nothing was attempted, so this
                        // is "skipped", not "failed".
                        default => $skipped++,
                    };
                } catch (AiProviderUnavailableException $exception) {
                    // The provider (not this story) is the problem: the
                    // generator already returned the job to Pending, so
                    // stop the batch rather than hammer it - the next
                    // scheduled run retries.
                    $skipped++;
                    $stoppedReason = $exception->getMessage();

                    break;
                } catch (Throwable $exception) {
                    $failed++;

                    Log::error('Editorial processing: unexpected error processing a job.', [
                        'editorial_job_id' => $job->id,
                        'story_id' => $job->story_id,
                        'exception' => $exception->getMessage(),
                    ]);

                    // generate() already marks a job Failed on every
                    // handled failure path; this only covers the
                    // unexpected case (e.g. the AI provider throwing
                    // instead of returning a failed AiResult) so the
                    // job never sits stuck in Running.
                    EditorialJob::whereKey($job->id)
                        ->where('status', EditorialJobStatus::Running)
                        ->update([
                            'status' => EditorialJobStatus::Failed,
                            'error' => 'Unexpected error: '.$exception->getMessage(),
                            'completed_at' => now(),
                        ]);
                }
            }

            $run->update([
                'status' => $failed === 0 ? AutomationRunStatus::Completed : AutomationRunStatus::Partial,
                'completed_at' => now(),
                'items_processed' => $processed,
                'items_failed' => $failed,
                'items_skipped' => $skipped,
                'metrics' => [
                    'jobs_completed' => $completed,
                    'stopped_reason' => $stoppedReason,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Editorial processing: run aborted unexpectedly.', [
                'exception' => $exception->getMessage(),
            ]);

            $run->update([
                'status' => AutomationRunStatus::Failed,
                'completed_at' => now(),
                'items_processed' => $processed,
                'items_failed' => $failed,
                'items_skipped' => $skipped,
                'error' => $exception->getMessage(),
                'metrics' => [
                    'jobs_completed' => $completed,
                ],
            ]);

            throw $exception;
        }

        return $run->refresh();
    }
}
