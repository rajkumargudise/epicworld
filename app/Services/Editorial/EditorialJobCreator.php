<?php

namespace App\Services\Editorial;

use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\EditorialJob;
use App\Models\Story;

/**
 * Creates the EditorialJob that will eventually turn a candidate Story
 * into a drafted Article (Milestone 5+). This service only establishes
 * the job and its lifecycle - it never invokes an AI provider, never
 * creates an Article, and never starts the job. Starting a job (moving
 * it to Running and the Story to Processing) is a distinct operation
 * for the editorial pipeline itself to perform.
 */
class EditorialJobCreator
{
    public const JOB_TYPE_ARTICLE_GENERATION = 'article_generation';

    /**
     * Create (or return the existing) EditorialJob for this Story and
     * job type. Idempotent: a Story that already has a job for this
     * purpose - pending, running, completed, or failed - never gets a
     * second one. Only a cancelled job is not considered a duplicate,
     * since cancellation is an explicit decision to abandon that
     * attempt rather than a failure awaiting retry.
     *
     * Returns null when the Story is not eligible at all (wrong status
     * or no source evidence), so the caller can distinguish "nothing to
     * do" from "already handled".
     */
    public function createFor(Story $story, string $jobType = self::JOB_TYPE_ARTICLE_GENERATION): ?EditorialJob
    {
        if (! $this->isEligible($story)) {
            return null;
        }

        $existing = EditorialJob::query()
            ->where('story_id', $story->id)
            ->where('job_type', $jobType)
            ->where('status', '!=', EditorialJobStatus::Cancelled)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // Deliberately not changing $story->status here. "Job created"
        // (pending) is distinct from "job started" (running), and only
        // the latter is a real state transition for the Story - moving
        // it to Processing before any work has actually begun would
        // make the Story's status lie about what happened. The Story
        // stays Candidate until the pipeline that processes jobs starts
        // one; a Candidate Story with a pending EditorialJob is a valid,
        // queryable state on its own.
        return EditorialJob::create([
            'story_id' => $story->id,
            'job_type' => $jobType,
            'status' => EditorialJobStatus::Pending,
            'attempts' => 0,
        ]);
    }

    public function isEligible(Story $story): bool
    {
        return $story->status === StoryStatus::Candidate
            && $story->sourceCount() > 0;
    }

    /**
     * Recover a failed job for another attempt. Reuses the same row
     * (rather than creating a new one) so attempts/error history stay
     * attached to one job, and so createFor()'s duplicate check keeps
     * working - a retried job is still "a job for this story and type"
     * the moment it's back to pending.
     */
    public function retry(EditorialJob $job): bool
    {
        if ($job->status !== EditorialJobStatus::Failed) {
            return false;
        }

        $job->update([
            'status' => EditorialJobStatus::Pending,
            'attempts' => $job->attempts + 1,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        return true;
    }
}
