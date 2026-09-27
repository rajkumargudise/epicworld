<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotsTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_robots_txt_is_available_and_allows_public_crawling(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Allow: /', false);
    }

    public function test_robots_txt_disallows_admin_login_and_search(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /search', false);
    }

    public function test_robots_txt_references_the_sitemap(): void
    {
        $this->get('/robots.txt')->assertSee('Sitemap: '.route('sitemap'), false);
    }

    public function test_robots_txt_does_not_block_public_content(): void
    {
        $content = $this->get('/robots.txt')->getContent();

        $this->assertStringNotContainsString('Disallow: /article', $content);
        $this->assertStringNotContainsString('Disallow: /category', $content);
        $this->assertStringNotContainsString('Disallow: /latest', $content);
        $this->assertStringNotContainsString('Disallow: /tag', $content);
    }
}
