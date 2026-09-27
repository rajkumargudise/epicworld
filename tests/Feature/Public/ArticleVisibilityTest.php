<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleVisibilityTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_a_published_article_is_publicly_visible(): void
    {
        $article = $this->publishedArticle(['title' => 'Visible headline']);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('Visible headline');
    }

    public function test_a_draft_article_is_not_publicly_reachable(): void
    {
        $article = $this->publishedArticle(['status' => ArticleStatus::Draft, 'published_at' => null]);

        $this->get(route('article.show', $article))->assertNotFound();
    }

    public function test_a_review_article_is_not_publicly_reachable(): void
    {
        $article = $this->publishedArticle(['status' => ArticleStatus::Review, 'published_at' => null]);

        $this->get(route('article.show', $article))->assertNotFound();
    }

    public function test_a_scheduled_article_is_not_publicly_reachable(): void
    {
        $article = $this->publishedArticle(['status' => ArticleStatus::Scheduled, 'published_at' => null]);

        $this->get(route('article.show', $article))->assertNotFound();
    }

    public function test_a_published_article_with_a_future_published_at_is_not_yet_publicly_reachable(): void
    {
        $article = $this->publishedArticle(['published_at' => now()->addDay()]);

        $this->get(route('article.show', $article))->assertNotFound();
    }

    public function test_a_sensitive_articles_story_does_not_block_public_visibility_once_published(): void
    {
        $topic = Topic::create([
            'name' => 'Elections',
            'slug' => 'elections-'.uniqid(),
            'is_sensitive' => true,
            'is_active' => true,
        ]);

        $story = Story::create([
            'topic_id' => $topic->id,
            'title' => 'A sensitive but reviewed story',
            'slug' => 'a-sensitive-story-'.uniqid(),
            'content_hash' => hash('sha256', uniqid()),
            'status' => StoryStatus::Published,
            'facts' => [],
        ]);

        $article = $this->publishedArticle([
            'story_id' => $story->id,
            'title' => 'Sensitive but legitimately published',
            'editorial_metadata' => [
                'sensitivity' => [
                    'sensitive' => true,
                    'reason' => 'topic',
                    'human_reviewed_at' => now()->subMinutes(5)->toIso8601String(),
                ],
            ],
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('Sensitive but legitimately published');
    }

    public function test_internal_editorial_metadata_never_appears_in_the_public_html(): void
    {
        $article = $this->publishedArticle([
            'editorial_metadata' => [
                'generation_method' => 'ai_assisted',
                'quality' => ['passed' => true, 'issues' => []],
                'sensitivity' => ['sensitive' => false, 'reason' => null],
                'human_edited_by' => 'Jamie Editor',
                'approved_at' => now()->toIso8601String(),
            ],
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertDontSee('generation_method', false);
        $response->assertDontSee('ai_assisted', false);
        $response->assertDontSee('human_edited_by', false);
        $response->assertDontSee('editorial_metadata', false);
        $response->assertDontSee('Jamie Editor', false);
    }

    public function test_an_unknown_article_slug_is_a_404(): void
    {
        $this->get('/article/does-not-exist')->assertNotFound();
    }
}
