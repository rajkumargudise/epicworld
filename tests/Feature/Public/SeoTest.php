<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_an_indexable_article_emits_canonical_and_open_graph_tags(): void
    {
        $article = $this->publishedArticle([
            'title' => 'An SEO-friendly headline',
            'allow_indexing' => true,
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('<title>An SEO-friendly headline', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical" href="'.route('article.show', $article).'"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('NewsArticle', false);
    }

    public function test_a_non_indexable_article_emits_noindex_and_suppresses_canonical_and_structured_data(): void
    {
        $article = $this->publishedArticle([
            'title' => 'A non-indexable article',
            'allow_indexing' => false,
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
        $response->assertDontSee('rel="canonical"', false);
        $response->assertDontSee('og:title', false);
        $response->assertDontSee('application/ld+json', false);
    }

    public function test_an_article_with_an_explicit_seo_title_and_description_uses_them(): void
    {
        $article = $this->publishedArticle([
            'title' => 'The regular headline',
            'seo_title' => 'A custom SEO title',
            'seo_description' => 'A custom SEO description.',
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertSee('<title>A custom SEO title', false);
        $response->assertSee('A custom SEO description.', false);
    }

    public function test_the_homepage_and_category_pages_carry_a_canonical_url(): void
    {
        $category = $this->category();
        $this->publishedArticle(['category_id' => $category->id]);

        $this->get(route('home'))->assertSee('rel="canonical" href="'.route('home').'"', false);
        $this->get(route('category.show', $category))
            ->assertSee('rel="canonical" href="'.route('category.show', $category).'"', false);
    }
}
