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

    public function test_article_json_ld_includes_article_section_and_keywords_when_available(): void
    {
        $category = $this->category(['name' => 'Robotics']);
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'A robotics story']);
        $tag = $this->tag(['name' => 'Automation']);
        $article->tags()->attach($tag->id);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('"articleSection":"Robotics"', false);
        $response->assertSee('"keywords":"Automation"', false);
    }

    public function test_article_json_ld_falls_back_to_the_site_as_author_and_the_brand_image(): void
    {
        $article = $this->publishedArticle(['title' => 'No byline or image on this one']);

        $html = $this->get(route('article.show', $article))->assertOk()->getContent();

        // No fabricated person: with no author the publisher itself is credited.
        $this->assertStringContainsString('"author":{"@type":"Organization"', str_replace('\\u0022', '"', $html) ?: $html) || $this->assertMatchesRegularExpression('/author.{0,40}Organization/', $html);
        // Google's article rich results need an image: the real brand card is used.
        $this->assertStringContainsString('/brand/og-default.jpg', $html);
        $this->assertStringContainsString('BreadcrumbList', $html);
    }

    public function test_article_json_ld_is_safely_encoded_against_script_context_breakout(): void
    {
        $article = $this->publishedArticle([
            'title' => 'Breaking out </script><script>alert(1)</script> of the tag',
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertDontSee('</script><script>alert(1)</script>', false);
    }

    public function test_the_homepage_carries_website_and_organization_structured_data(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"WebSite"', false);
        $response->assertSee('SearchAction', false);
    }

    public function test_organization_schema_uses_the_real_brand_logo_and_invents_no_social_profiles(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('/brand/icon-512.png', false);
        $response->assertDontSee('"sameAs"', false);
        $this->assertFileExists(public_path('brand/icon-512.png'));
        $this->assertFileExists(public_path('brand/og-default.jpg'));
    }

    public function test_a_second_page_of_latest_is_noindex_follow_with_a_self_referencing_canonical(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle(['title' => "Latest page two {$i}", 'published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('latest', ['page' => 2]));

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, follow"', false);
        $response->assertSee('rel="canonical" href="'.route('latest', ['page' => 2]).'"', false);
    }

    public function test_page_one_of_latest_stays_indexable_with_a_clean_canonical(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle(['title' => "Latest page one {$i}", 'published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('latest'));

        $response->assertOk();
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('rel="canonical" href="'.route('latest').'"', false);
    }

    public function test_a_second_page_of_a_category_is_noindex_follow_with_a_self_referencing_canonical(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle(['category_id' => $category->id, 'title' => "Cat page two {$i}", 'published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('category.show', [$category, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, follow"', false);
        $response->assertSee('rel="canonical" href="'.route('category.show', [$category, 'page' => 2]).'"', false);
    }

    public function test_canonical_urls_use_the_configured_app_url_and_https_scheme(): void
    {
        config(['app.url' => 'https://epicworld.example']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="canonical" href="https://epicworld.example', false);
    }
}
