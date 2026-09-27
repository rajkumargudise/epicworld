<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Services\Editorial\ArticleQualityEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleQualityEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_article_passes(): void
    {
        $article = $this->completeArticle();

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['issues']);
    }

    public function test_an_empty_title_fails(): void
    {
        $article = $this->completeArticle(['title' => '   ']);

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertFalse($result['passed']);
        $this->assertContains('missing_title', $result['issues']);
    }

    public function test_empty_content_fails(): void
    {
        $article = $this->completeArticle(['content' => '']);

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertContains('missing_content', $result['issues']);
    }

    public function test_content_below_the_minimum_length_fails(): void
    {
        $article = $this->completeArticle(['content' => 'Too short.']);

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertContains('content_too_short', $result['issues']);
    }

    public function test_missing_category_fails(): void
    {
        $article = $this->completeArticle(['category_id' => null]);

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertContains('missing_category', $result['issues']);
    }

    public function test_a_story_with_no_supporting_facts_fails(): void
    {
        $story = Story::create([
            'title' => 'No facts',
            'slug' => 'no-facts-'.uniqid(),
            'content_hash' => hash('sha256', 'no-facts-'.uniqid()),
            'facts' => [],
        ]);
        $article = $this->completeArticle(['story_id' => $story->id]);

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertContains('no_supporting_facts', $result['issues']);
    }

    public function test_multiple_issues_are_all_reported(): void
    {
        $article = $this->completeArticle(['title' => '', 'content' => '', 'category_id' => null]);

        $result = app(ArticleQualityEvaluator::class)->evaluate($article);

        $this->assertFalse($result['passed']);
        $this->assertContains('missing_title', $result['issues']);
        $this->assertContains('missing_content', $result['issues']);
        $this->assertContains('missing_category', $result['issues']);
    }

    private function completeArticle(array $overrides = []): Article
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
        ], $overrides));
    }
}
