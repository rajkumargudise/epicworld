<?php

namespace Tests\Feature\Admin;

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryManagementTest extends TestCase
{
    use CreatesEditorialFixtures;
    use RefreshDatabase;

    public function test_the_story_list_shows_the_expected_editorial_fields(): void
    {
        $user = User::factory()->editor()->create();
        $topic = $this->topic(['name' => 'Elections', 'is_sensitive' => true]);
        $story = $this->story([
            'title' => 'A closely watched election story',
            'topic_id' => $topic->id,
            'importance' => 9,
        ]);

        $response = $this->actingAs($user)->get('/admin/stories');

        $response->assertOk();
        $response->assertSee('A closely watched election story');
        $response->assertSee('Elections');
        $response->assertSee($story->status->label());
        $response->assertSee('9');
        $response->assertSee('Sensitive');
    }

    public function test_the_story_detail_page_shows_the_fact_sheet_and_source_provenance(): void
    {
        $user = User::factory()->editor()->create();
        $story = $this->story([
            'facts' => [
                ['source_id' => 1, 'source_name' => 'Example Wire', 'reported' => ['headline' => 'Something happened']],
            ],
        ]);

        $source = Source::create([
            'name' => 'Example Wire',
            'slug' => 'example-wire-'.uniqid(),
            'domain' => 'example.com',
            'is_trusted' => true,
        ]);

        $story->sources()->attach($source->id, [
            'source_url' => 'https://example.com/a',
            'title' => 'Something happened',
            'discovered_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/admin/stories/{$story->id}");

        $response->assertOk();
        $response->assertSee('Something happened');
        $response->assertSee('Example Wire');
        $response->assertSee('headline');
    }

    public function test_a_guest_cannot_view_the_story_queue(): void
    {
        $this->get('/admin/stories')->assertRedirect('/login');
    }
}
