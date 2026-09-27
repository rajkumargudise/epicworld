<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndTagTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_a_category_page_only_shows_its_own_published_articles(): void
    {
        $technology = $this->category(['name' => 'Technology']);
        $business = $this->category(['name' => 'Business']);

        $this->publishedArticle(['category_id' => $technology->id, 'title' => 'A technology story']);
        $this->publishedArticle(['category_id' => $business->id, 'title' => 'A business story']);

        $response = $this->get(route('category.show', $technology));

        $response->assertOk();
        $response->assertSee('A technology story');
        $response->assertDontSee('A business story');
    }

    public function test_a_category_page_never_shows_unpublished_articles(): void
    {
        $category = $this->category();
        $this->publishedArticle([
            'category_id' => $category->id,
            'title' => 'A draft in this category',
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('category.show', $category));

        $response->assertOk();
        $response->assertDontSee('A draft in this category');
    }

    public function test_an_inactive_category_is_not_publicly_reachable(): void
    {
        $category = $this->category(['is_active' => false]);

        $this->get("/category/{$category->slug}")->assertNotFound();
    }

    public function test_an_unknown_category_slug_is_a_404(): void
    {
        $this->get('/category/does-not-exist')->assertNotFound();
    }

    public function test_a_tag_page_only_shows_articles_carrying_that_tag(): void
    {
        $tagged = $this->publishedArticle(['title' => 'A tagged article']);
        $untagged = $this->publishedArticle(['title' => 'An untagged article']);

        $tag = $this->tag(['name' => 'Cloud']);
        $tagged->tags()->attach($tag->id);

        $response = $this->get(route('tag.show', $tag));

        $response->assertOk();
        $response->assertSee('A tagged article');
        $response->assertDontSee('An untagged article');
    }

    public function test_a_tag_page_never_shows_unpublished_articles(): void
    {
        $tag = $this->tag();
        $draft = $this->publishedArticle([
            'title' => 'A draft with this tag',
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
        $draft->tags()->attach($tag->id);

        $response = $this->get(route('tag.show', $tag));

        $response->assertOk();
        $response->assertDontSee('A draft with this tag');
    }

    public function test_an_unknown_tag_slug_is_a_404(): void
    {
        $this->get('/tag/does-not-exist')->assertNotFound();
    }
}
