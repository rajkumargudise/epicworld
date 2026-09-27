<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedArticlesTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_related_articles_exclude_the_current_article(): void
    {
        $category = $this->category();
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'The current article']);
        $sibling = $this->publishedArticle(['category_id' => $category->id, 'title' => 'A sibling article']);

        $related = $article->relatedArticles();

        $this->assertFalse($related->contains('id', $article->id));
        $this->assertTrue($related->contains('id', $sibling->id));
    }

    public function test_related_articles_prefer_the_same_category(): void
    {
        $categoryA = $this->category(['name' => 'Technology']);
        $categoryB = $this->category(['name' => 'Business']);

        $article = $this->publishedArticle(['category_id' => $categoryA->id, 'title' => 'Current tech article']);
        $sameCategory = $this->publishedArticle(['category_id' => $categoryA->id, 'title' => 'Another tech article']);
        $otherCategory = $this->publishedArticle(['category_id' => $categoryB->id, 'title' => 'A business article']);

        $related = $article->relatedArticles(1);

        $this->assertSame($sameCategory->id, $related->first()->id);
        $this->assertFalse($related->contains('id', $otherCategory->id));
    }

    public function test_related_articles_fall_back_to_shared_tags_when_the_category_has_nothing_else(): void
    {
        $category = $this->category();
        $otherCategory = $this->category(['name' => 'Other']);
        $tag = $this->tag(['name' => 'Semiconductors']);

        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'Current article']);
        $article->tags()->attach($tag->id);

        $tagMatch = $this->publishedArticle(['category_id' => $otherCategory->id, 'title' => 'Tag-matched article']);
        $tagMatch->tags()->attach($tag->id);

        $related = $article->relatedArticles(4);

        $this->assertTrue($related->contains('id', $tagMatch->id));
    }

    public function test_related_articles_fall_back_to_recent_articles_when_nothing_else_matches(): void
    {
        $category = $this->category();
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'Current article']);

        $otherCategory = $this->category(['name' => 'Unrelated']);
        $recent = $this->publishedArticle(['category_id' => $otherCategory->id, 'title' => 'Recent unrelated article']);

        $related = $article->relatedArticles(4);

        $this->assertTrue($related->contains('id', $recent->id));
    }

    public function test_related_articles_are_only_publicly_visible(): void
    {
        $category = $this->category();
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'Current article']);

        $draft = $this->publishedArticle([
            'category_id' => $category->id,
            'title' => 'A draft that must not appear',
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
        $future = $this->publishedArticle([
            'category_id' => $category->id,
            'title' => 'A future article that must not appear',
            'published_at' => now()->addDay(),
        ]);

        $related = $article->relatedArticles(10);

        $this->assertFalse($related->contains('id', $draft->id));
        $this->assertFalse($related->contains('id', $future->id));
    }

    public function test_related_articles_are_bounded_to_the_requested_limit(): void
    {
        $category = $this->category();
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'Current article']);

        for ($i = 0; $i < 10; $i++) {
            $this->publishedArticle(['category_id' => $category->id, 'title' => "Sibling {$i}"]);
        }

        $this->assertCount(4, $article->relatedArticles(4));
        $this->assertCount(2, $article->relatedArticles(2));
    }

    public function test_related_articles_render_on_the_article_page(): void
    {
        $category = $this->category();
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'Main article']);
        $sibling = $this->publishedArticle(['category_id' => $category->id, 'title' => 'A related sibling']);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('Related articles');
        $response->assertSee($sibling->title);
    }

    public function test_the_article_page_shows_no_related_section_when_nothing_qualifies(): void
    {
        $article = $this->publishedArticle(['title' => 'Only article in the archive']);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertDontSee('Related articles');
    }
}
