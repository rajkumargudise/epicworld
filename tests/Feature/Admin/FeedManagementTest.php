<?php

namespace Tests\Feature\Admin;

use App\Models\Source;
use App\Models\SourceFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_feed_list_shows_health_fields_an_administrator_needs_to_diagnose_a_feed(): void
    {
        $user = User::factory()->editor()->create();
        $feed = $this->feed([
            'name' => 'Diagnosable Feed',
            'last_fetched_at' => now()->subMinutes(5),
            'last_success_at' => now()->subHours(2),
            'last_failure_at' => now()->subMinutes(5),
            'last_error' => 'Feed request returned HTTP 503.',
        ]);

        $response = $this->actingAs($user)->get('/admin/feeds');

        $response->assertOk();
        $response->assertSee('Diagnosable Feed');
        $response->assertSee($feed->source->name);
        $response->assertSee('Feed request returned HTTP 503.');
        $response->assertSee('Active');
    }

    public function test_an_inactive_feed_is_labeled_inactive(): void
    {
        $user = User::factory()->editor()->create();
        $this->feed(['name' => 'Paused Feed', 'is_active' => false]);

        $response = $this->actingAs($user)->get('/admin/feeds');

        $response->assertOk();
        $response->assertSee('Paused Feed');
        $response->assertSee('Inactive');
    }

    public function test_a_guest_cannot_view_feed_health(): void
    {
        $this->get('/admin/feeds')->assertRedirect('/login');
    }

    public function test_a_non_editor_cannot_view_feed_health(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/feeds')->assertForbidden();
    }

    private function feed(array $overrides = []): SourceFeed
    {
        $source = Source::create([
            'name' => 'Example Source',
            'slug' => 'example-source-'.uniqid(),
        ]);

        return SourceFeed::create(array_merge([
            'source_id' => $source->id,
            'name' => 'Example Feed',
            'url' => 'https://example.com/feed-'.uniqid().'.xml',
        ], $overrides));
    }
}
