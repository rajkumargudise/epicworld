<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Services\Editorial\PublicationDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_passing_draft_moves_to_review(): void
    {
        $article = $this->draftArticle();

        $decided = app(PublicationDecision::class)->decide($article);

        $this->assertSame(ArticleStatus::Review, $decided->status);
        $this->assertTrue($decided->editorial_metadata['quality']['passed']);
        $this->assertSame([], $decided->editorial_metadata['quality']['issues']);
        $this->assertArrayHasKey('evaluated_at', $decided->editorial_metadata['quality']);
    }

    public function test_a_failing_draft_stays_in_draft_with_issues_recorded(): void
    {
        $article = $this->draftArticle(['content' => '']);

        $decided = app(PublicationDecision::class)->decide($article);

        $this->assertSame(ArticleStatus::Draft, $decided->status);
        $this->assertFalse($decided->editorial_metadata['quality']['passed']);
        $this->assertContains('missing_content', $decided->editorial_metadata['quality']['issues']);
    }

    public function test_an_article_already_in_review_is_not_moved_backward_or_reevaluated_into_draft(): void
    {
        $article = $this->draftArticle(['status' => ArticleStatus::Review]);

        $decided = app(PublicationDecision::class)->decide($article);

        $this->assertSame(ArticleStatus::Review, $decided->status);
    }

    public function test_a_published_article_is_left_alone_even_if_it_would_now_fail(): void
    {
        $article = $this->draftArticle(['status' => ArticleStatus::Published, 'content' => '']);

        $decided = app(PublicationDecision::class)->decide($article);

        $this->assertSame(ArticleStatus::Published, $decided->status);
        // The evaluation result is still recorded for visibility, but
        // publication itself is never reversed by this service.
        $this->assertFalse($decided->editorial_metadata['quality']['passed']);
    }

    public function test_a_passing_draft_advances_its_candidate_story_to_review(): void
    {
        $article = $this->draftArticle(storyOverrides: ['status' => StoryStatus::Candidate]);

        app(PublicationDecision::class)->decide($article);

        $this->assertSame(StoryStatus::Review, $article->story->refresh()->status);
    }

    public function test_a_passing_draft_advances_its_processing_story_to_review(): void
    {
        $article = $this->draftArticle(storyOverrides: ['status' => StoryStatus::Processing]);

        app(PublicationDecision::class)->decide($article);

        $this->assertSame(StoryStatus::Review, $article->story->refresh()->status);
    }

    public function test_it_never_regresses_a_story_already_past_review(): void
    {
        // Draft article whose Story was already moved to Approved by
        // an editor (a later, more-advanced state than this decision
        // knows how to make) - the Story is left exactly as is.
        $article = $this->draftArticle(storyOverrides: ['status' => StoryStatus::Approved]);

        app(PublicationDecision::class)->decide($article);

        $this->assertSame(StoryStatus::Approved, $article->story->refresh()->status);
    }

    public function test_a_failing_draft_does_not_touch_the_story_status(): void
    {
        $article = $this->draftArticle(['content' => ''], storyOverrides: ['status' => StoryStatus::Candidate]);

        app(PublicationDecision::class)->decide($article);

        $this->assertSame(StoryStatus::Candidate, $article->story->refresh()->status);
    }

    private function draftArticle(array $overrides = [], array $storyOverrides = []): Article
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
            'status' => ArticleStatus::Draft,
        ], $overrides));
    }
}
