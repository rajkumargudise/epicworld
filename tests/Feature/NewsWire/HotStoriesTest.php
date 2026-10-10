<?php

namespace Tests\Feature\NewsWire;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Models\WireItem;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\NewsWire\HotStories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HotStoriesTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $source, string $title, string $scope = 'news', int $minutesAgo = 10): WireItem
    {
        return WireItem::create([
            'kind' => 'article', 'scope' => $scope, 'source' => $source, 'title' => $title,
            'summary' => 'Summary text.', 'url' => 'https://x.test/'.md5($source.$title), 'url_hash' => md5($source.$title),
            'published_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_the_same_story_from_several_outlets_is_a_hot_story_and_solo_stories_are_not(): void
    {
        $this->item('The Hindu', 'Supreme Court hears plea on Delhi pollution curbs');
        $this->item('NDTV', 'Delhi pollution: Supreme Court hears plea on curbs');
        $this->item('Times of India', 'Supreme Court takes up Delhi pollution curbs plea');
        $this->item('The Hindu', 'Cricket team announced for Australia tour squad');

        $hot = app(HotStories::class)->top();

        $this->assertCount(1, $hot);
        $this->assertSame(3, $hot[0]['count']);
        $this->assertEqualsCanonicalizing(['The Hindu', 'NDTV', 'Times of India'], $hot[0]['sources']);
    }

    public function test_the_same_outlet_repeating_itself_does_not_count_as_many_outlets(): void
    {
        $this->item('The Hindu', 'Supreme Court hears plea on Delhi pollution curbs');
        $this->item('The Hindu', 'Delhi pollution: Supreme Court hears plea on curbs', 'news', 20);

        $this->assertCount(0, app(HotStories::class)->top());
    }

    public function test_old_items_are_ignored(): void
    {
        $this->item('The Hindu', 'Supreme Court hears plea on Delhi pollution curbs', 'news', 60 * 40);
        $this->item('NDTV', 'Delhi pollution: Supreme Court hears plea on curbs', 'news', 60 * 40);

        $this->assertCount(0, app(HotStories::class)->top());
    }

    public function test_the_home_page_leads_with_india_and_shows_hot_stories_and_a_slow_ticker(): void
    {
        $this->item('The Hindu', 'Supreme Court hears plea on Delhi pollution curbs');
        $this->item('NDTV', 'Delhi pollution: Supreme Court hears plea on curbs');
        $this->item('BBC News', 'A very different world headline appears here today', 'world');

        $html = $this->get('/')->assertOk()->assertSee('India now')->assertSee('Hot stories')->assertSee('2 outlets')->getContent();

        // India is the first tab and the ticker is slow (>= 60s, scaled by headline count).
        $this->assertLessThan(strpos($html, 'data-tab="world"'), strpos($html, 'data-tab="news"'));
        $this->assertMatchesRegularExpression('/--ticker-duration: (\d+)s/', $html);
        preg_match('/--ticker-duration: (\d+)s/', $html, $m);
        $this->assertGreaterThanOrEqual(60, (int) $m[1]);
    }

    public function test_the_wire_refreshes_every_minute_by_default(): void
    {
        $this->assertSame(1, config('newswire.fetch_every_minutes'));
        $this->assertSame(2, config('newswire.stale_after_minutes'));
    }

    public function test_an_editor_can_turn_a_headline_into_a_full_draft_and_nobody_else_can(): void
    {
        config(['ai.default' => 'fake', 'editorial.min_content_length' => 40]);
        Http::fake(['*' => Http::response(['results' => []], 200)]);
        Category::firstOrCreate(['slug' => 'india'], ['name' => 'India', 'is_active' => true, 'sort_order' => 1]);
        $headline = $this->item('The Hindu', 'Parliament passes the new data protection amendment');

        $body = "An opening paragraph that explains the story in some detail for readers.\n\n## What happened\n\n".str_repeat('Detailed explanation of what happened and why it matters. ', 6);
        app(FakeAiProvider::class)->push(AiResult::success('fake', 'm', ['title' => 'Parliament passes data protection amendment', 'body' => $body, 'key_points' => ['One', 'Two'], 'tags' => ['Parliament'], 'image_query' => 'parliament']));

        $this->get($headline->path())->assertOk()->assertDontSee('Write full article');
        $this->post("/admin/wire/{$headline->id}/write")->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'contributor']))->post("/admin/wire/{$headline->id}/write")->assertForbidden();

        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->post("/admin/wire/{$headline->id}/write")->assertRedirect();

        $article = Article::firstOrFail();
        $this->assertNotSame('published', $article->status->value);
        $this->assertSame($headline->id, $article->editorial_metadata['wire_item_id']);
        $this->assertSame('The Hindu', $article->editorial_metadata['source_links'][0]['name']);
        $this->actingAs($editor)->get($headline->path())->assertOk()->assertSee('Write full article');
    }
}
