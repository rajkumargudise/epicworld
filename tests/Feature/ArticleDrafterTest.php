<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use App\Models\Story;
use App\Models\Topic;
use App\Services\Editorial\ArticleDrafter;
use App\Services\Editorial\FactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleDrafterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_drafts_an_article_from_the_storys_fact_sheet(): void
    {
        $story = $this->storyWithFacts(summary: 'A concise summary.');

        $article = app(ArticleDrafter::class)->draftFor($story);

        $this->assertNotNull($article);
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertSame($story->id, $article->story_id);
        $this->assertSame($story->title, $article->title);
        $this->assertSame('A concise summary.', $article->dek);
        $this->assertStringContainsString('According to Example Source', $article->content);
        $this->assertStringContainsString('Reported headline', $article->content);
        $this->assertSame(ArticleDrafter::GENERATION_METHOD, $article->editorial_metadata['generation_method']);
        $this->assertSame(1, $article->editorial_metadata['source_count']);
    }

    public function test_it_returns_null_when_the_story_has_no_facts(): void
    {
        $story = Story::create([
            'title' => 'No facts yet',
            'slug' => 'no-facts-'.uniqid(),
            'content_hash' => hash('sha256', 'no-facts-'.uniqid()),
        ]);

        $article = app(ArticleDrafter::class)->draftFor($story);

        $this->assertNull($article);
        $this->assertSame(0, Article::count());
    }

    public function test_it_does_not_redraft_a_story_that_already_has_an_article(): void
    {
        $story = $this->storyWithFacts();
        $drafter = app(ArticleDrafter::class);
        $first = $drafter->draftFor($story);

        $second = $drafter->draftFor($story->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Article::count());
    }

    public function test_it_derives_the_category_from_the_storys_topic(): void
    {
        $category = Category::create(['name' => 'World', 'slug' => 'world-'.uniqid()]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Elections', 'slug' => 'elections-'.uniqid()]);
        $story = $this->storyWithFacts(topicId: $topic->id);

        $article = app(ArticleDrafter::class)->draftFor($story);

        $this->assertSame($category->id, $article->category_id);
    }

    public function test_slug_collisions_are_resolved_deterministically(): void
    {
        $storyA = $this->storyWithFacts(title: 'Shared Title');
        $storyB = $this->storyWithFacts(title: 'Shared Title');
        $drafter = app(ArticleDrafter::class);

        $articleA = $drafter->draftFor($storyA);
        $articleB = $drafter->draftFor($storyB);

        $this->assertNotSame($articleA->slug, $articleB->slug);
    }

    private function storyWithFacts(?string $summary = null, ?int $topicId = null, string $title = 'Story with facts'): Story
    {
        $story = Story::create([
            'topic_id' => $topicId,
            'title' => $title,
            'slug' => Str::slug($title).'-'.uniqid(),
            'summary' => $summary,
            'content_hash' => hash('sha256', $title.uniqid()),
        ]);

        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source-'.uniqid()]);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/'.uniqid(),
            'title' => 'Reported headline',
            'discovered_at' => now(),
        ]);

        app(FactExtractor::class)->extract($story);

        return $story->refresh();
    }
}
