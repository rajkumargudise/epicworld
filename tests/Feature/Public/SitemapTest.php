<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_the_sitemap_is_available_as_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('xml', $response->headers->get('Content-Type'));
        $response->assertSee('<urlset', false);
    }

    public function test_the_sitemap_includes_published_articles(): void
    {
        $article = $this->publishedArticle(['title' => 'A sitemap-eligible article']);

        $this->get('/sitemap.xml')->assertSee(route('article.show', $article), false);
    }

    public function test_the_sitemap_excludes_draft_articles(): void
    {
        $draft = $this->publishedArticle(['status' => ArticleStatus::Draft, 'published_at' => null]);

        $this->get('/sitemap.xml')->assertDontSee('/article/'.$draft->slug, false);
    }

    public function test_the_sitemap_excludes_review_articles(): void
    {
        $review = $this->publishedArticle(['status' => ArticleStatus::Review, 'published_at' => null]);

        $this->get('/sitemap.xml')->assertDontSee('/article/'.$review->slug, false);
    }

    public function test_the_sitemap_excludes_scheduled_articles(): void
    {
        $scheduled = $this->publishedArticle(['status' => ArticleStatus::Scheduled, 'published_at' => null]);

        $this->get('/sitemap.xml')->assertDontSee('/article/'.$scheduled->slug, false);
    }

    public function test_the_sitemap_excludes_future_published_articles(): void
    {
        $future = $this->publishedArticle(['published_at' => now()->addDay()]);

        $this->get('/sitemap.xml')->assertDontSee('/article/'.$future->slug, false);
    }

    public function test_the_sitemap_excludes_archived_articles(): void
    {
        $archived = $this->publishedArticle(['status' => ArticleStatus::Archived]);

        $this->get('/sitemap.xml')->assertDontSee('/article/'.$archived->slug, false);
    }

    public function test_the_sitemap_excludes_articles_with_indexing_disabled(): void
    {
        $article = $this->publishedArticle(['allow_indexing' => false]);

        $this->get('/sitemap.xml')->assertDontSee('/article/'.$article->slug, false);
    }

    public function test_the_sitemap_never_includes_search_or_admin_urls(): void
    {
        $this->publishedArticle();

        $content = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/search', $content);
        $this->assertStringNotContainsString('/admin', $content);
    }

    public function test_the_sitemap_includes_a_category_that_has_publicly_visible_articles(): void
    {
        $category = $this->category(['name' => 'Sitemap category']);
        $this->publishedArticle(['category_id' => $category->id]);

        $this->get('/sitemap.xml')->assertSee(route('category.show', $category), false);
    }

    public function test_the_sitemap_excludes_a_category_with_no_publicly_visible_articles(): void
    {
        $category = $this->category(['name' => 'Empty category']);

        $this->get('/sitemap.xml')->assertDontSee('/category/'.$category->slug, false);
    }

    public function test_the_sitemap_excludes_an_inactive_category_even_with_articles(): void
    {
        $category = $this->category(['name' => 'Retired', 'is_active' => false]);
        $this->publishedArticle(['category_id' => $category->id]);

        $this->get('/sitemap.xml')->assertDontSee('/category/'.$category->slug, false);
    }

    public function test_the_sitemap_includes_a_tag_that_has_publicly_visible_articles(): void
    {
        $tag = $this->tag(['name' => 'Sitemap tag']);
        $article = $this->publishedArticle();
        $article->tags()->attach($tag->id);

        $this->get('/sitemap.xml')->assertSee(route('tag.show', $tag), false);
    }

    public function test_the_sitemap_excludes_a_tag_with_no_publicly_visible_articles(): void
    {
        $tag = $this->tag(['name' => 'Unused tag']);

        $this->get('/sitemap.xml')->assertDontSee('/tag/'.$tag->slug, false);
    }

    public function test_the_sitemap_includes_the_homepage_and_latest_feed_when_content_exists(): void
    {
        $this->publishedArticle();

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('home'), false);
        $response->assertSee(route('latest'), false);
    }

    public function test_the_sitemap_omits_discovery_urls_when_there_is_no_indexable_content(): void
    {
        $content = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('<loc>'.route('home').'</loc>', $content);
    }

    public function test_the_sitemap_query_count_stays_bounded_as_the_archive_grows(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 30; $i++) {
            $this->publishedArticle(['category_id' => $category->id, 'title' => "Bounded {$i}"]);
        }

        DB::enableQueryLog();
        $this->get('/sitemap.xml')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount);
    }
}
