<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\Topic;
use App\Services\Editorial\SensitiveContentRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SensitiveContentRouterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function sensitiveCategoryNames(): array
    {
        return [
            'politics' => ['Politics'],
            'elections' => ['Elections'],
            'finance/investing' => ['Finance & Investing'],
            'health/medical' => ['Health & Medical'],
            'legal' => ['Legal'],
            'disasters/casualties' => ['Disasters & Casualties'],
            'allegations/accusations' => ['Allegations & Accusations'],
        ];
    }

    #[DataProvider('sensitiveCategoryNames')]
    public function test_a_story_under_a_sensitive_category_is_routed_to_review(string $categoryName): void
    {
        $category = Category::create(['name' => $categoryName, 'slug' => str($categoryName)->slug().'-'.uniqid(), 'is_sensitive' => true]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => $categoryName.' Coverage', 'slug' => 'topic-'.uniqid()]);
        $story = $this->story($topic);

        $decision = app(SensitiveContentRouter::class)->evaluate($story);

        $this->assertTrue($decision['sensitive']);
        $this->assertSame('category', $decision['reason']);
        $this->assertSame($category->id, $decision['category_id']);
        $this->assertSame($category->name, $decision['category_name']);
    }

    public function test_a_story_under_a_sensitive_topic_is_routed_to_review_even_if_its_category_is_not_flagged(): void
    {
        $category = Category::create(['name' => 'Sports', 'slug' => 'sports-'.uniqid()]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Doping Allegations', 'slug' => 'doping-'.uniqid(), 'is_sensitive' => true]);
        $story = $this->story($topic);

        $decision = app(SensitiveContentRouter::class)->evaluate($story);

        $this->assertTrue($decision['sensitive']);
        $this->assertSame('topic', $decision['reason']);
        $this->assertSame($topic->id, $decision['topic_id']);
    }

    public function test_a_normal_low_risk_story_is_not_sensitive(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology-'.uniqid()]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Gadgets', 'slug' => 'gadgets-'.uniqid()]);
        $story = $this->story($topic);

        $decision = app(SensitiveContentRouter::class)->evaluate($story);

        $this->assertFalse($decision['sensitive']);
        $this->assertNull($decision['reason']);
    }

    public function test_a_story_with_no_topic_is_conservatively_treated_as_sensitive(): void
    {
        $story = Story::create([
            'title' => 'Unclassified story',
            'slug' => 'unclassified-'.uniqid(),
            'content_hash' => hash('sha256', 'unclassified-'.uniqid()),
        ]);

        $decision = app(SensitiveContentRouter::class)->evaluate($story);

        $this->assertTrue($decision['sensitive']);
        $this->assertSame('unclassified', $decision['reason']);
    }

    public function test_annotate_persists_the_decision_on_the_article_and_preserves_a_prior_human_review(): void
    {
        $category = Category::create(['name' => 'Finance', 'slug' => 'finance-'.uniqid(), 'is_sensitive' => true]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Markets', 'slug' => 'markets-'.uniqid()]);
        $story = $this->story($topic);
        $article = Article::create([
            'story_id' => $story->id,
            'title' => 'A market story',
            'slug' => 'a-market-story-'.uniqid(),
            'content' => 'Content.',
        ]);
        $router = app(SensitiveContentRouter::class);
        $router->confirmHumanReview($article);

        $decision = $router->annotate($article->refresh());

        $this->assertTrue($decision['sensitive']);
        $this->assertNotNull($decision['human_reviewed_at']);
    }

    public function test_requires_review_is_false_once_a_sensitive_article_has_been_human_reviewed(): void
    {
        $category = Category::create(['name' => 'Legal', 'slug' => 'legal-'.uniqid(), 'is_sensitive' => true]);
        $topic = Topic::create(['category_id' => $category->id, 'name' => 'Litigation', 'slug' => 'litigation-'.uniqid()]);
        $story = $this->story($topic);
        $article = Article::create([
            'story_id' => $story->id,
            'title' => 'A legal story',
            'slug' => 'a-legal-story-'.uniqid(),
            'content' => 'Content.',
        ]);
        $router = app(SensitiveContentRouter::class);

        $this->assertTrue($router->requiresReview($article));

        $router->confirmHumanReview($article->refresh());

        $this->assertFalse($router->requiresReview($article->refresh()));
    }

    private function story(Topic $topic): Story
    {
        return Story::create([
            'topic_id' => $topic->id,
            'title' => 'A story',
            'slug' => 'a-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-story-'.uniqid()),
        ]);
    }
}
