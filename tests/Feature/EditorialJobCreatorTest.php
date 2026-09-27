<?php

namespace Tests\Feature;

use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\EditorialJob;
use App\Models\Source;
use App\Models\Story;
use App\Services\Editorial\EditorialJobCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialJobCreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_candidate_story_gets_a_pending_editorial_job(): void
    {
        $story = $this->candidateStory();

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertNotNull($job);
        $this->assertSame(EditorialJobStatus::Pending, $job->status);
        $this->assertSame(EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION, $job->job_type);
        $this->assertSame(0, $job->attempts);
        $this->assertSame($story->id, $job->story_id);
        // Job creation alone never moves the Story off Candidate.
        $this->assertSame(StoryStatus::Candidate, $story->refresh()->status);
    }

    public function test_repeated_creation_for_the_same_story_does_not_duplicate_the_job(): void
    {
        $story = $this->candidateStory();
        $creator = app(EditorialJobCreator::class);

        $first = $creator->createFor($story);
        $second = $creator->createFor($story);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EditorialJob::count());
    }

    public function test_existing_pending_job_blocks_a_duplicate(): void
    {
        $story = $this->candidateStory();
        $existing = $this->jobFor($story, EditorialJobStatus::Pending);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertSame($existing->id, $job->id);
        $this->assertSame(1, EditorialJob::count());
    }

    public function test_existing_running_job_blocks_a_duplicate(): void
    {
        $story = $this->candidateStory();
        $existing = $this->jobFor($story, EditorialJobStatus::Running);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertSame($existing->id, $job->id);
        $this->assertSame(1, EditorialJob::count());
    }

    public function test_existing_completed_job_blocks_a_duplicate(): void
    {
        $story = $this->candidateStory();
        $existing = $this->jobFor($story, EditorialJobStatus::Completed);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertSame($existing->id, $job->id);
        $this->assertSame(1, EditorialJob::count());
    }

    public function test_existing_failed_job_blocks_a_duplicate_and_is_returned_for_retry(): void
    {
        $story = $this->candidateStory();
        $existing = $this->jobFor($story, EditorialJobStatus::Failed);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertSame($existing->id, $job->id);
        $this->assertSame(1, EditorialJob::count());
    }

    public function test_cancelled_job_does_not_block_a_fresh_job(): void
    {
        $story = $this->candidateStory();
        $this->jobFor($story, EditorialJobStatus::Cancelled);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertNotNull($job);
        $this->assertSame(EditorialJobStatus::Pending, $job->status);
        $this->assertSame(2, EditorialJob::count());
    }

    public function test_retry_moves_a_failed_job_back_to_pending_and_increments_attempts(): void
    {
        $story = $this->candidateStory();
        $job = $this->jobFor($story, EditorialJobStatus::Failed);
        $job->update(['attempts' => 1, 'error' => 'Provider timed out.', 'started_at' => now(), 'completed_at' => now()]);

        $retried = app(EditorialJobCreator::class)->retry($job);

        $this->assertTrue($retried);
        $job->refresh();
        $this->assertSame(EditorialJobStatus::Pending, $job->status);
        $this->assertSame(2, $job->attempts);
        $this->assertNull($job->error);
        $this->assertNull($job->started_at);
        $this->assertNull($job->completed_at);
    }

    public function test_retry_refuses_a_job_that_is_not_failed(): void
    {
        $story = $this->candidateStory();
        $job = $this->jobFor($story, EditorialJobStatus::Pending);

        $retried = app(EditorialJobCreator::class)->retry($job);

        $this->assertFalse($retried);
        $this->assertSame(EditorialJobStatus::Pending, $job->refresh()->status);
    }

    public function test_rejected_story_does_not_get_a_job(): void
    {
        $this->assertNoJobIsCreatedFor(StoryStatus::Rejected);
    }

    public function test_published_story_does_not_get_a_job(): void
    {
        $this->assertNoJobIsCreatedFor(StoryStatus::Published);
    }

    public function test_review_story_does_not_get_a_job(): void
    {
        $this->assertNoJobIsCreatedFor(StoryStatus::Review);
    }

    public function test_approved_story_does_not_get_a_job(): void
    {
        $this->assertNoJobIsCreatedFor(StoryStatus::Approved);
    }

    public function test_processing_story_does_not_get_a_second_job(): void
    {
        $this->assertNoJobIsCreatedFor(StoryStatus::Processing);
    }

    public function test_discovered_story_is_not_yet_eligible(): void
    {
        $this->assertNoJobIsCreatedFor(StoryStatus::Discovered);
    }

    public function test_candidate_story_without_source_evidence_is_not_eligible(): void
    {
        $story = Story::create([
            'title' => 'No evidence',
            'slug' => 'no-evidence',
            'content_hash' => hash('sha256', 'no-evidence'),
            'status' => StoryStatus::Candidate,
        ]);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertNull($job);
        $this->assertSame(0, EditorialJob::count());
    }

    private function assertNoJobIsCreatedFor(StoryStatus $status): void
    {
        $story = $this->candidateStory();
        $story->update(['status' => $status]);

        $job = app(EditorialJobCreator::class)->createFor($story);

        $this->assertNull($job);
        $this->assertSame(0, EditorialJob::count());
    }

    private function candidateStory(): Story
    {
        $story = Story::create([
            'title' => 'Candidate story',
            'slug' => 'candidate-story-'.uniqid(),
            'content_hash' => hash('sha256', 'candidate-story-'.uniqid()),
            'status' => StoryStatus::Candidate,
        ]);

        $source = Source::create(['name' => 'source-'.uniqid(), 'slug' => 'source-'.uniqid()]);
        $source->stories()->attach($story, ['source_url' => 'https://example.com/story']);

        return $story;
    }

    private function jobFor(Story $story, EditorialJobStatus $status): EditorialJob
    {
        return EditorialJob::create([
            'story_id' => $story->id,
            'job_type' => EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION,
            'status' => $status,
        ]);
    }
}
