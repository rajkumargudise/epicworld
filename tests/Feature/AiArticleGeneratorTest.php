<?php

namespace Tests\Feature;

use App\Enums\AiResultStatus;
use App\Enums\ArticleStatus;
use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\EditorialJob;
use App\Models\Source;
use App\Models\Story;
use App\Models\Topic;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Editorial\AiArticleGenerator;
use App\Services\Editorial\EditorialJobCreator;
use App\Services\Editorial\FactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiArticleGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.default' => 'fake']);
    }

    public function test_it_generates_and_completes_the_job_when_output_is_fully_supported_by_evidence(): void
    {
        [$story, $job] = $this->candidateWithJob(withTopic: true);
        $this->fakeAiSuccess([
            'title' => 'The Bridge Reopens Tomorrow',
            'dek' => 'A short deck summarizing the reopening.',
            'body' => str_repeat('The bridge reopens tomorrow after months of repair work. ', 3),
            'citations' => ['The bridge reopens tomorrow'],
        ]);

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(EditorialJobStatus::Completed, $result->status);
        $this->assertNotNull($result->article_id);
        $this->assertSame('fake', $result->provider);

        $article = Article::findOrFail($result->article_id);
        $this->assertSame('The Bridge Reopens Tomorrow', $article->title);
        $this->assertSame('ai_assisted', $article->editorial_metadata['generation_method']);
        $this->assertSame(['The bridge reopens tomorrow'], $article->editorial_metadata['citations']);
        // Quality gates passed (has category, content, facts), so both
        // the Article and its Story move to Review.
        $this->assertSame(ArticleStatus::Review, $article->status);
        $this->assertSame(StoryStatus::Review, $story->refresh()->status);
    }

    public function test_a_provider_failure_fails_the_job_and_reverts_the_story_to_candidate(): void
    {
        [$story, $job] = $this->candidateWithJob();
        app(FakeAiProvider::class)->push(
            AiResult::failure(AiResultStatus::ProviderError, 'fake', null, 'Simulated provider outage.'),
        );

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(EditorialJobStatus::Failed, $result->status);
        $this->assertStringContainsString('Simulated provider outage', $result->error);
        $this->assertSame(StoryStatus::Candidate, $story->refresh()->status);
        $this->assertSame(0, Article::count());
    }

    public function test_output_with_no_citations_is_rejected(): void
    {
        [$story, $job] = $this->candidateWithJob();
        $this->fakeAiSuccess([
            'title' => 'A title',
            'body' => str_repeat('Some generated body text. ', 3),
            'citations' => [],
        ]);

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(EditorialJobStatus::Failed, $result->status);
        $this->assertStringContainsString('no citations', $result->error);
        $this->assertSame(StoryStatus::Candidate, $story->refresh()->status);
        $this->assertSame(0, Article::count());
    }

    public function test_output_citing_a_claim_absent_from_the_evidence_is_rejected(): void
    {
        [$story, $job] = $this->candidateWithJob();
        $this->fakeAiSuccess([
            'title' => 'A title',
            'body' => str_repeat('Some generated body text. ', 3),
            'citations' => ['This claim was never reported by any source'],
        ]);

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(EditorialJobStatus::Failed, $result->status);
        $this->assertStringContainsString('not present in the evidence ledger', $result->error);
        $this->assertSame(0, Article::count());
    }

    public function test_a_story_with_no_facts_fails_clearly_without_calling_the_provider(): void
    {
        $story = Story::create([
            'title' => 'No evidence',
            'slug' => 'no-evidence-'.uniqid(),
            'content_hash' => hash('sha256', 'no-evidence-'.uniqid()),
            'status' => StoryStatus::Candidate,
        ]);
        $job = EditorialJob::create([
            'story_id' => $story->id,
            'job_type' => EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION,
            'status' => EditorialJobStatus::Pending,
        ]);

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(EditorialJobStatus::Failed, $result->status);
        $this->assertStringContainsString('no supporting facts', $result->error);
        $this->assertSame(0, app(FakeAiProvider::class)->callCount());
    }

    public function test_it_is_a_no_op_for_a_job_that_is_not_pending(): void
    {
        [, $job] = $this->candidateWithJob();
        $job->update(['status' => EditorialJobStatus::Completed]);

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(EditorialJobStatus::Completed, $result->status);
        $this->assertSame(0, app(FakeAiProvider::class)->callCount());
    }

    public function test_it_is_a_no_op_for_a_story_that_is_not_a_candidate(): void
    {
        [$story, $job] = $this->candidateWithJob();
        $story->update(['status' => StoryStatus::Published]);

        $result = app(AiArticleGenerator::class)->generate($job->refresh());

        $this->assertSame(EditorialJobStatus::Pending, $result->status);
        $this->assertSame(0, app(FakeAiProvider::class)->callCount());
    }

    public function test_it_updates_an_existing_article_instead_of_creating_a_duplicate(): void
    {
        [$story, $job] = $this->candidateWithJob();
        $originalSlug = 'existing-slug-'.uniqid();
        $existing = Article::create([
            'story_id' => $story->id,
            'title' => 'Old deterministic title',
            'slug' => $originalSlug,
            'content' => 'Old content.',
            'status' => ArticleStatus::Draft,
        ]);
        $this->fakeAiSuccess([
            'title' => 'New AI title',
            'body' => str_repeat('Reported headline in more words. ', 3),
            'citations' => ['Reported headline'],
        ]);

        $result = app(AiArticleGenerator::class)->generate($job);

        $this->assertSame(1, Article::count());
        $this->assertSame($existing->id, $result->article_id);
        $existing->refresh();
        $this->assertSame('New AI title', $existing->title);
        // The existing slug is preserved rather than regenerated.
        $this->assertSame($originalSlug, $existing->slug);
    }

    /**
     * @return array{0: Story, 1: EditorialJob}
     */
    private function candidateWithJob(bool $withTopic = false): array
    {
        $topicId = null;

        if ($withTopic) {
            $category = Category::create(['name' => 'World', 'slug' => 'world-'.uniqid()]);
            $topic = Topic::create(['category_id' => $category->id, 'name' => 'Infrastructure', 'slug' => 'infrastructure-'.uniqid()]);
            $topicId = $topic->id;
        }

        $story = Story::create([
            'topic_id' => $topicId,
            'title' => 'Bridge story',
            'slug' => 'bridge-story-'.uniqid(),
            'content_hash' => hash('sha256', 'bridge-story-'.uniqid()),
            'status' => StoryStatus::Candidate,
        ]);
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source-'.uniqid()]);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/'.uniqid(),
            'title' => 'Reported headline',
            'summary' => 'The bridge reopens tomorrow',
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
