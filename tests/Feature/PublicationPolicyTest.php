<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Services\Editorial\PublicationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_passing_review_article_is_approved_to_scheduled(): void
    {
        $article = $this->article(ArticleStatus::Review);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertTrue($approved);
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);
        $this->assertArrayHasKey('approved_at', $article->editorial_metadata);
    }

    public function test_approval_refuses_a_draft_article(): void
    {
        $article = $this->article(ArticleStatus::Draft);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertFalse($approved);
        $this->assertSame(ArticleStatus::Draft, $article->refresh()->status);
    }

    public function test_approval_refuses_an_already_scheduled_article(): void
    {
        $article = $this->article(ArticleStatus::Scheduled);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertFalse($approved);
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);
    }

    public function test_approval_re_evaluates_quality_and_refuses_a_now_failing_article(): void
    {
        $article = $this->article(ArticleStatus::Review, ['content' => '']);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertFalse($approved);
        // Left in Review, not pushed back to Draft - it needs a look,
        // not a restart.
        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
        $this->assertFalse($article->editorial_metadata['quality']['passed']);
    }

    public function test_a_scheduled_article_is_published(): void
    {
        $article = $this->article(ArticleStatus::Scheduled, storyOverrides: ['status' => StoryStatus::Approved]);

        $published = app(PublicationPolicy::class)->publish($article);

        $this->assertTrue($published);
        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(StoryStatus::Published, $article->story->refresh()->status);
    }

    public function test_publishing_refuses_a_review_article(): void
    {
        $article = $this->article(ArticleStatus::Review);

        $published = app(PublicationPolicy::class)->publish($article);

        $this->assertFalse($published);
        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
    }

    public function test_publishing_an_already_published_article_is_a_no_op_that_preserves_the_original_timestamp(): void
    {
        $article = $this->article(ArticleStatus::Published);
        $article->update(['published_at' => '2026-01-01 00:00:00']);
        $originalTimestamp = $article->published_at;

        $published = app(PublicationPolicy::class)->publish($article);

        $this->assertTrue($published);
        $this->assertTrue($article->refresh()->published_at->equalTo($originalTimestamp));
    }

    private function article(ArticleStatus $status, array $overrides = [], array $storyOverrides = []): Article
    {
        $category = Category::create(['name' => 'World', 'slug' => 'world-'.uniqid()]);
        $story = Story::create(array_merge([
            'title' => 'A well-sourced story',
            'slug' => 'a-well-sourced-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-well-sourced-story-'.uniqid()),
            'facts' => [['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'Reported']]],
        ], $storyOverrides));

        return Article::create(array_merge([
            'story_id' => $story->id,
            'category_id' => $category->id,
            'title' => 'A complete article',
            'slug' => 'a-complete-article-'.uniqid(),
            'content' => str_repeat('This is enough content to pass the minimum length check. ', 2),
            'status' => $status,
        ], $overrides));
    }
}
