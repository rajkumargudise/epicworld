<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
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

    private function draftArticle(array $overrides = []): Article
    {
        $category = Category::create(['name' => 'World', 'slug' => 'world-'.uniqid()]);
        $story = Story::create([
            'title' => 'A well-sourced story',
            'slug' => 'a-well-sourced-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-well-sourced-story-'.uniqid()),
            'facts' => [['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'Reported']]],
        ]);

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
