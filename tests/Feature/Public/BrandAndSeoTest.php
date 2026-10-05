<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandAndSeoTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_every_brand_file_exists(): void
    {
        foreach (['logo-white.png', 'logo-white.webp', 'logo-black.png', 'logo-black.webp', 'favicon-16x16.png', 'favicon-32x32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'og-default.jpg', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path('brand/'.$file), $file);
        }
        $this->assertFileExists(public_path('favicon.ico'));

        $manifest = json_decode(file_get_contents(public_path('brand/site.webmanifest')), true);
        $this->assertSame('EPIC World', $manifest['name']);
        $this->assertCount(3, $manifest['icons']);
    }

    public function test_the_head_links_the_icons_manifest_and_feed_and_the_header_shows_the_logo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['/favicon.ico', '/brand/favicon-32x32.png', '/brand/apple-touch-icon.png', '/brand/site.webmanifest', route('feed')] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        $this->assertStringContainsString('/brand/logo-white.png', $html);
        $this->assertStringContainsString('/brand/logo-black.png', $html);
        $this->assertStringContainsString('alt="EPIC World"', $html);
        $this->assertStringContainsString('width="', $html); // explicit logo size - no layout shift
    }

    public function test_pages_without_their_own_image_share_the_brand_card(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('property="og:image" content="'.url('/brand/og-default.jpg').'"', $html);
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertStringContainsString('<title>About EPIC World', $html);
    }

    public function test_titles_get_the_site_name_when_they_fit(): void
    {
        $this->assertStringContainsString('<title>Contact us | EPIC World</title>', $this->get('/contact')->getContent());
    }

    public function test_the_rss_feed_lists_published_stories_only(): void
    {
        $live = $this->publishedArticle(['title' => 'Visible in the feed & more']);
        $this->publishedArticle(['title' => 'A draft', 'status' => \App\Enums\ArticleStatus::Draft, 'published_at' => null]);

        $response = $this->get('/feed')->assertOk();

        $this->assertStringContainsString('application/rss+xml', $response->headers->get('Content-Type'));
        $response->assertSee('<rss version="2.0"', false)
            ->assertSee('Visible in the feed &amp; more', false)
            ->assertSee(route('article.show', $live), false)
            ->assertDontSee('A draft');
        $this->assertNotFalse(simplexml_load_string($response->getContent()), 'feed must be well-formed XML');
    }

    public function test_the_sitemap_includes_article_images(): void
    {
        $this->publishedArticle(['featured_image' => 'https://img.example/pic.jpg', 'title' => 'With picture']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:image=', $xml);
        $this->assertStringContainsString('<image:loc>https://img.example/pic.jpg</image:loc>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_article_pages_carry_news_article_and_breadcrumb_schema_with_the_publisher_logo(): void
    {
        $article = $this->publishedArticle(['title' => 'Schema check']);

        $html = $this->get(route('article.show', $article))->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"NewsArticle"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('/brand/icon-512.png', $html);
        $this->assertStringContainsString('"wordCount"', $html);
    }

    public function test_hsts_is_sent_on_https_but_never_on_staging_or_plain_http(): void
    {
        $this->get('https://epicworld.in/')->assertHeader('Strict-Transport-Security', 'max-age=15552000');
        $this->get('https://staging.epicworld.in/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('http://epicworld.in/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_the_404_page_is_helpful_and_branded(): void
    {
        $this->get('/definitely-not-a-page-xyz')->assertNotFound()
            ->assertSee("couldn't find that page", false)
            ->assertSee('Search EPIC World', false)
            ->assertSee('noindex', false);
    }
}
