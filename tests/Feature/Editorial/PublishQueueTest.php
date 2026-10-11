<?php

namespace Tests\Feature\Editorial;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use App\Services\Editorial\PublicationPolicy;
use App\Services\Editorial\PublishQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PublishQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['editorial.min_content_length' => 40, 'editorial.publish_time' => '15:00']);
    }

    private function make(array $attributes): Article
    {
        return Article::create($attributes + [
            'title' => 'Article '.uniqid(),
            'slug' => 'article-'.uniqid(),
            'content' => str_repeat('A full paragraph of useful, original text for the reader. ', 12),
            'status' => ArticleStatus::Draft,
        ]);
    }

    private function approved(string $title, string $approvedAt): Article
    {
        return $this->make([
            'title' => $title,
            'status' => ArticleStatus::Scheduled,
            'published_at' => null,
            'editorial_metadata' => ['source' => 'ai_blog', 'approved_at' => $approvedAt],
        ]);
    }

    public function test_the_queue_releases_one_article_per_day_in_approval_order_once_the_slot_arrives(): void
    {
        $first = $this->approved('First approved', '2026-10-10T08:00:00+00:00');
        $second = $this->approved('Second approved', '2026-10-10T08:05:00+00:00');

        // 14:00 IST: before the slot - nothing happens.
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 12)->setTime(14, 0)->utc());
        $this->assertNull(app(PublishQueue::class)->publishDue());

        // 15:05 IST: the slot has arrived.
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 12)->setTime(15, 5)->utc());
        $this->assertSame($first->id, app(PublishQueue::class)->publishDue()?->id);
        $this->assertSame(ArticleStatus::Published, $first->refresh()->status);

        // Same day, later: today already has its post.
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 12)->setTime(18, 0)->utc());
        $this->assertNull(app(PublishQueue::class)->publishDue());
        $this->assertSame(ArticleStatus::Scheduled, $second->refresh()->status);

        // Next day's slot releases the second one.
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 13)->setTime(15, 1)->utc());
        $this->assertSame($second->id, app(PublishQueue::class)->publishDue()?->id);
    }

    public function test_a_day_that_already_has_a_dated_post_is_left_alone_and_the_queue_fills_the_free_days(): void
    {
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 12)->setTime(9, 0)->utc());

        // Tomorrow already has a post with its own date.
        $this->make([
            'status' => ArticleStatus::Published,
            'published_at' => now('Asia/Kolkata')->addDay()->setTime(15, 0)->utc(),
        ]);
        $a = $this->approved('Queued A', '2026-10-11T08:00:00+00:00');
        $b = $this->approved('Queued B', '2026-10-11T08:01:00+00:00');

        $projected = app(PublishQueue::class)->projected();

        $this->assertSame(['2026-10-12', '2026-10-14'], $projected->map(fn ($row) => $row['at']->toDateString())->all());
        $this->assertSame([$a->id, $b->id], $projected->map(fn ($row) => $row['article']->id)->all());
    }

    public function test_the_scheduled_command_runs_the_queue(): void
    {
        $article = $this->approved('Queued', '2026-10-10T08:00:00+00:00');
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 10, 12)->setTime(15, 30)->utc());

        $this->assertSame(0, Artisan::call('articles:publish-queue'));

        $this->assertSame(ArticleStatus::Published, $article->refresh()->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_an_editor_can_approve_selected_reviews_into_the_queue_without_publishing_them_now(): void
    {
        $editor = User::factory()->editor()->create();
        $review = $this->make(['category_id' => \App\Models\Category::create(['name' => 'Health', 'slug' => 'health', 'is_active' => true, 'sort_order' => 1])->id, 'editorial_metadata' => ['source' => 'ai_blog'], 'status' => ArticleStatus::Review, 'content' => str_repeat('A full paragraph of useful text. ', 20)]);

        $this->actingAs($editor)->post(route('admin.review.queue'), ['ids' => [$review->id]])->assertRedirect();

        $review->refresh();
        $this->assertSame(ArticleStatus::Scheduled, $review->status);
        $this->assertNull($review->published_at);
        $this->assertNotNull(app(PublicationPolicy::class));
    }
}
