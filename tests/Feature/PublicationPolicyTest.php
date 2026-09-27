<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\Topic;
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

    public function test_approval_refuses_an_article_whose_topic_is_flagged_sensitive(): void
    {
        $category = Category::create(['name' => 'News', 'slug' => 'news-'.uniqid()]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Elections', 'slug' => 'elections-'.uniqid(), 'is_sensitive' => true]);
        $article = $this->article(ArticleStatus::Review, topic: $topic);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertFalse($approved);
        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
        $this->assertTrue($article->editorial_metadata['sensitivity']['sensitive']);
        $this->assertSame('topic', $article->editorial_metadata['sensitivity']['reason']);
    }

    public function test_approval_refuses_an_article_whose_category_is_flagged_sensitive(): void
    {
        $category = Category::create(['name' => 'Health', 'slug' => 'health-'.uniqid(), 'is_sensitive' => true]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Public Health', 'slug' => 'public-health-'.uniqid()]);
        $article = $this->article(ArticleStatus::Review, topic: $topic);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertFalse($approved);
        $this->assertSame('category', $article->editorial_metadata['sensitivity']['reason']);
    }

    public function test_approval_refuses_an_unclassified_story(): void
    {
        $article = $this->article(ArticleStatus::Review, storyOverrides: ['topic_id' => null]);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertFalse($approved);
        $this->assertTrue($article->editorial_metadata['sensitivity']['sensitive']);
        $this->assertSame('unclassified', $article->editorial_metadata['sensitivity']['reason']);
    }

    public function test_a_normal_low_risk_story_is_not_treated_as_sensitive(): void
    {
        $article = $this->article(ArticleStatus::Review);

        $approved = app(PublicationPolicy::class)->approve($article);

        $this->assertTrue($approved);
        $this->assertFalse($article->refresh()->editorial_metadata['sensitivity']['sensitive']);
    }

    public function test_confirming_sensitive_review_unblocks_approval(): void
    {
        $category = Category::create(['name' => 'Politics', 'slug' => 'politics-'.uniqid(), 'is_sensitive' => true]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'National Politics', 'slug' => 'national-politics-'.uniqid()]);
        $article = $this->article(ArticleStatus::Review, topic: $topic);
        $policy = app(PublicationPolicy::class);
        $this->assertFalse($policy->approve($article));

        $policy->confirmSensitiveReview($article->refresh());
        $approved = $policy->approve($article->refresh());

        $this->assertTrue($approved);
        $this->assertNotNull($article->refresh()->editorial_metadata['sensitivity']['human_reviewed_at']);
    }

    public function test_publish_refuses_a_scheduled_article_that_still_requires_sensitive_review(): void
    {
        // Simulates a bypass attempt: an Article reaches Scheduled
        // (e.g. a direct status edit) without ever clearing sensitive
        // review through approve(). publish() must catch this on its
        // own rather than trusting that approve() was the only way in.
        $category = Category::create(['name' => 'Legal', 'slug' => 'legal-'.uniqid(), 'is_sensitive' => true]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Court Cases', 'slug' => 'court-cases-'.uniqid()]);
        $article = $this->article(ArticleStatus::Scheduled, topic: $topic, storyOverrides: ['status' => StoryStatus::Approved]);

        $published = app(PublicationPolicy::class)->publish($article);

        $this->assertFalse($published);
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);
    }

    private function article(ArticleStatus $status, array $overrides = [], array $storyOverrides = [], ?Topic $topic = null): Article
    {
        $category = Category::create(['name' => 'World', 'slug' => 'world-'.uniqid()]);
        // A non-sensitive Topic by default, so these tests exercise
        // approve()/publish() itself rather than the sensitivity
        // gate - the dedicated sensitivity tests below pass their
        // own $topic (or none, for the unclassified case).
        $topic ??= Topic::create(['category_id' => $category->id, 'name' => 'General', 'slug' => 'general-'.uniqid()]);

        $story = Story::create(array_merge([
            'topic_id' => $topic->id,
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
