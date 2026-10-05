<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyUrlRedirectTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_a_root_level_wordpress_slug_redirects_permanently(): void
    {
        $article = $this->publishedArticle(['slug' => 'my-old-post']);

        $this->get('/my-old-post/')->assertStatus(301)->assertRedirect(route('article.show', $article));
    }

    public function test_a_dated_wordpress_permalink_redirects_permanently(): void
    {
        $article = $this->publishedArticle(['slug' => 'dated-post']);

        $this->get('/2026/04/dated-post/')->assertStatus(301)->assertRedirect(route('article.show', $article));
    }

    public function test_an_old_post_id_link_redirects_using_the_imported_wordpress_id(): void
    {
        $article = $this->publishedArticle(['editorial_metadata' => ['wp_post_id' => 77]]);

        $this->get('/?p=77')->assertStatus(301)->assertRedirect(route('article.show', $article));
    }

    public function test_an_unpublished_article_is_not_redirected(): void
    {
        $this->publishedArticle(['slug' => 'still-a-draft', 'status' => ArticleStatus::Draft, 'published_at' => null]);

        $this->get('/still-a-draft/')->assertNotFound();
    }

    public function test_old_wordpress_sitemaps_and_feeds_redirect_to_the_new_ones(): void
    {
        foreach (['/wp-sitemap.xml', '/sitemap_index.xml', '/post-sitemap.xml', '/wp-sitemap-posts-post-1.xml'] as $old) {
            $this->get($old)->assertStatus(301)->assertRedirect(route('sitemap'));
        }

        foreach (['/comments/feed', '/rss', '/rss.xml'] as $old) {
            $this->get($old)->assertStatus(301)->assertRedirect(route('feed'));
        }
    }

    public function test_an_unknown_path_is_a_plain_404(): void
    {
        $this->get('/no-such-thing')->assertNotFound();
    }
}
