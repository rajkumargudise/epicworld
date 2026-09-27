<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_the_latest_feed_paginates_rather_than_loading_every_article(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle([
                'title' => "Article {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('latest'));

        $response->assertOk();
        // 20 per page: the 21st-oldest article should not be on page one.
        $response->assertSee('Article 0');
        $response->assertDontSee('Article 24');
        $response->assertSee('page=2', false);
    }

    public function test_the_latest_feed_orders_deterministically_across_repeated_requests(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle([
                'title' => "Ordered {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $extractTitleOrder = fn (string $html) => preg_match_all('/Ordered \d+/', $html, $matches) ? $matches[0] : [];

        $firstPass = $extractTitleOrder($this->get(route('latest'))->getContent());
        $secondPass = $extractTitleOrder($this->get(route('latest'))->getContent());

        $this->assertNotEmpty($firstPass);
        $this->assertSame($firstPass, $secondPass);
    }

    public function test_a_second_page_returns_different_articles_than_the_first(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle([
                'title' => "Paged {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $pageOne = $this->get(route('latest', ['page' => 1]));
        $pageTwo = $this->get(route('latest', ['page' => 2]));

        $pageOne->assertOk();
        $pageTwo->assertOk();
        $pageTwo->assertSee('Paged 24');
        $pageTwo->assertDontSee('Paged 0');
    }
}
