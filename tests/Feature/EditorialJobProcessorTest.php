<?php

namespace Tests\Feature;

use App\Enums\AiResultStatus;
use App\Enums\ArticleStatus;
use App\Enums\AutomationRunStatus;
use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\EditorialJob;
use App\Models\Source;
use App\Models\Story;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Editorial\EditorialJobCreator;
use App\Services\Editorial\EditorialJobProcessor;
use App\Services\Editorial\FactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Milestone 16 bridge itself: EditorialJobProcessor calling
 * the existing, untouched AiArticleGenerator for each pending job, with
 * per-job failure isolation and accurate AutomationRun counters -
 * mirroring DiscoveryRunnerAutomationTest's shape for the processing
 * side of the pipeline. Every test here also proves the absolute
 * boundary: nothing this milestone adds ever approves or publishes an
 * Article.
 */
class EditorialJobProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.default' => 'fake']);
    }

    public function test_a_pending_job_is_processed_into_a_draft_article(): void
    {
        [$story, $job] = $this->pendingJobWithEvidence();
        $this->fakeAiSuccess([
            'title' => 'A generated title',
            'body' => str_repeat('Generated body content. ', 3),
            'citations' => ['Reported headline'],
        ]);

        $run = app(EditorialJobProcessor::class)->process();

        $this->assertSame(AutomationRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->items_processed);
        $this->assertSame(0, $run->items_failed);
        $this->assertSame(1, $run->metrics['jobs_completed']);
        $this->assertSame(EditorialJobStatus::Completed, $job->refresh()->status);
        $this->assertNotNull($job->article_id);
    }

    public function test_a_rate_limited_provider_leaves_jobs_pending_and_stops_the_batch(): void
    {
        [, $first] = $this->pendingJobWithEvidence();
        [, $second] = $this->pendingJobWithEvidence();
        app(FakeAiProvider::class)->push(
            AiResult::failure(AiResultStatus::RateLimited, 'fake', null, 'quota exceeded'),
        );

        $run = app(EditorialJobProcessor::class)->process();

        $this->assertSame(0, $run->items_failed);
        $this->assertCount(1, app(FakeAiProvider::class)->calls(), 'the batch must stop after the first unavailable response');
        foreach ([$first, $second] as $job) {
            $job->refresh();
            $this->assertSame(EditorialJobStatus::Pending, $job->status);
            $this->assertSame(0, $job->attempts);
            $this->assertSame(StoryStatus::Candidate, $job->story->status);
        }
        $this->assertStringContainsString('Waiting', $first->error);
    }

    public function test_one_failing_job_does_not_stop_processing_for_the_others(): void
    {
        [, $badJob] = $this->pendingJobWithEvidence();
        [, $goodJob] = $this->pendingJobWithEvidence();
        app(FakeAiProvider::class)->push(
            AiResult::failure(AiResultStatus::ProviderError, 'fake', null, 'Simulated provider outage.'),
        );
        app(FakeAiProvider::class)->push(
            AiResult::success('fake', null, [
                'title' => 'A generated title',
                'body' => str_repeat('Generated body content. ', 3),
                'citations' => ['Reported headline'],
            ]),
        );

        $run = app(EditorialJobProcessor::class)->process();

        $this->assertSame(AutomationRunStatus::Partial, $run->status);
        $this->assertSame(2, $run->items_processed);
        $this->assertSame(1, $run->items_failed);
        $this->assertSame(1, $run->metrics['jobs_completed']);
        $this->assertSame(EditorialJobStatus::Failed, $badJob->refresh()->status);
        $this->assertSame(EditorialJobStatus::Completed, $goodJob->refresh()->status);
    }

    public function test_a_job_whose_story_is_no_longer_a_candidate_is_counted_as_skipped(): void
    {
        [$story, $job] = $this->pendingJobWithEvidence();
        $story->update(['status' => StoryStatus::Published]);

        $run = app(EditorialJobProcessor::class)->process();

        $this->assertSame(1, $run->items_processed);
        $this->assertSame(0, $run->items_failed);
        $this->assertSame(1, $run->items_skipped);
        $this->assertSame(0, app(FakeAiProvider::class)->callCount());
    }

    public function test_the_limit_bounds_how_many_pending_jobs_are_fetched(): void
    {
        $this->pendingJobWithEvidence();
        $this->pendingJobWithEvidence();
        $this->pendingJobWithEvidence();

        $run = app(EditorialJobProcessor::class)->process(limit: 2);

        $this->assertSame(2, $run->items_processed);
        $this->assertSame(1, EditorialJob::where('status', EditorialJobStatus::Pending)->count());
    }

    public function test_it_never_approves_or_publishes_the_generated_article(): void
    {
        [, $job] = $this->pendingJobWithEvidence();
        $this->fakeAiSuccess([
            'title' => 'A generated title',
            'body' => str_repeat('Generated body content. ', 3),
            'citations' => ['Reported headline'],
        ]);

        app(EditorialJobProcessor::class)->process();

        $article = Article::findOrFail($job->refresh()->article_id);
        $this->assertContains($article->status, [ArticleStatus::Draft, ArticleStatus::Review]);
        $this->assertNotSame(ArticleStatus::Scheduled, $article->status);
        $this->assertNotSame(ArticleStatus::Published, $article->status);
        $this->assertNull($article->published_at);
    }

    public function test_running_the_processor_with_no_pending_jobs_completes_cleanly(): void
    {
        $run = app(EditorialJobProcessor::class)->process();

        $this->assertSame(AutomationRunStatus::Completed, $run->status);
        $this->assertSame(0, $run->items_processed);
    }

    /**
     * @return array{0: Story, 1: EditorialJob}
     */
    private function pendingJobWithEvidence(): array
    {
        $story = Story::create([
            'title' => 'A story',
            'slug' => 'a-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-story-'.uniqid()),
            'status' => StoryStatus::Candidate,
        ]);
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source-'.uniqid()]);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/'.uniqid(),
            'title' => 'Reported headline',
            'summary' => 'Reported headline summary',
            'discovered_at' => now(),
        ]);
        app(FactExtractor::class)->extract($story);

        $job = EditorialJob::create([
            'story_id' => $story->id,
            'job_type' => EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION,
            'status' => EditorialJobStatus::Pending,
        ]);

        return [$story->refresh(), $job];
    }

    private function fakeAiSuccess(array $data): void
    {
        app(FakeAiProvider::class)->push(AiResult::success('fake', null, $data));
    }
}
