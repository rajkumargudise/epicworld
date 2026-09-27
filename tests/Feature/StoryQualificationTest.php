<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Models\Source;
use App\Models\Story;
use App\Services\Stories\StoryQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_can_have_multiple_source_observations(): void
    {
        $story = $this->story();
        $firstSource = $this->source('first-source');
        $secondSource = $this->source('second-source');

        $firstSource->stories()->attach($story, [
            'source_url' => 'https://first.example/story',
            'external_id' => 'first-1',
        ]);
        $secondSource->stories()->attach($story, [
            'source_url' => 'https://second.example/story',
            'external_id' => 'second-1',
        ]);

        $this->assertSame(2, $story->sourceCount());
    }

    public function test_source_observation_data_stays_on_the_pivot(): void
    {
        $story = $this->story();
        $source = $this->source('first-source');
        $source->stories()->attach($story, [
            'source_url' => 'https://first.example/story',
            'external_id' => 'first-1',
            'title' => 'Source title',
            'summary' => 'Source summary',
        ]);

        $observation = $story->sources()->first()->pivot;

        $this->assertSame('https://first.example/story', $observation->source_url);
        $this->assertSame('first-1', $observation->external_id);
        $this->assertSame('Source title', $observation->title);
        $this->assertSame('Source summary', $observation->summary);
        $this->assertNull($story->getAttribute('source_url'));
    }

    public function test_trusted_source_detection_and_latest_observation_work(): void
    {
        $story = $this->story();
        $source = $this->source('trusted-source', true);
        $source->stories()->attach($story, [
            'source_url' => 'https://trusted.example/story',
            'discovered_at' => '2026-09-25 12:00:00',
        ]);
        $this->source('later-source')->stories()->attach($story, [
            'source_url' => 'https://later.example/story',
            'discovered_at' => '2026-09-25 13:00:00',
        ]);

        $this->assertTrue($story->hasTrustedSource());
        $this->assertSame(
            '2026-09-25 13:00:00',
            $story->latestSourceObservationAt()?->format('Y-m-d H:i:s')
        );
    }

    public function test_reingesting_same_source_observation_is_idempotent(): void
    {
        $story = $this->story();
        $source = $this->source('first-source');
        $attributes = [
            'source_url' => 'https://first.example/story',
            'external_id' => 'first-1',
        ];

        $source->stories()->syncWithoutDetaching([$story->id => $attributes]);
        $source->stories()->syncWithoutDetaching([$story->id => $attributes]);

        $this->assertSame(1, $story->sources()->count());
        $this->assertSame(1, $source->stories()->count());
    }

    public function test_valid_discovered_story_qualifies_as_candidate(): void
    {
        $story = $this->story();
        $source = $this->source('first-source');
        $source->stories()->attach($story, [
            'source_url' => 'https://first.example/story',
        ]);

        $qualified = app(StoryQualificationService::class)->qualify($story);

        $this->assertTrue($qualified);
        $this->assertSame(StoryStatus::Candidate, $story->refresh()->status);
    }

    public function test_missing_title_does_not_qualify(): void
    {
        $story = $this->story(title: '');
        $this->attachObservation($story);

        $this->assertFalse(app(StoryQualificationService::class)->qualify($story));
        $this->assertSame(StoryStatus::Discovered, $story->refresh()->status);
    }

    public function test_missing_source_observation_does_not_qualify(): void
    {
        $story = $this->story();

        $this->assertFalse(app(StoryQualificationService::class)->qualify($story));
        $this->assertSame(StoryStatus::Discovered, $story->refresh()->status);
    }

    public function test_rejected_story_is_not_moved_to_candidate(): void
    {
        $story = $this->story(status: StoryStatus::Rejected);
        $this->attachObservation($story);

        $this->assertFalse(app(StoryQualificationService::class)->qualify($story));
        $this->assertSame(StoryStatus::Rejected, $story->refresh()->status);
    }

    public function test_later_story_states_are_not_downgraded(): void
    {
        foreach ([
            StoryStatus::Processing,
            StoryStatus::Review,
            StoryStatus::Approved,
            StoryStatus::Published,
        ] as $status) {
            $story = $this->story(status: $status);
            $this->attachObservation($story, $status->value.'-source');

            $this->assertFalse(app(StoryQualificationService::class)->qualify($story));
            $this->assertSame($status, $story->refresh()->status);
        }
    }

    public function test_qualification_does_not_create_or_modify_article_content(): void
    {
        $story = $this->story();
        $this->attachObservation($story);

        app(StoryQualificationService::class)->qualify($story);

        $this->assertDatabaseCount('articles', 0);
        $this->assertNull($story->refresh()->article);
    }

    private function story(
        string $title = 'Story title',
        StoryStatus $status = StoryStatus::Discovered,
    ): Story {
        return Story::create([
            'title' => $title,
            'slug' => str_replace(' ', '-', strtolower($title)).'-'.uniqid(),
            'content_hash' => hash('sha256', $title.uniqid()),
            'status' => $status,
        ]);
    }

    private function source(string $slug, bool $trusted = false): Source
    {
        return Source::create([
            'name' => $slug,
            'slug' => $slug,
            'is_trusted' => $trusted,
        ]);
    }

    private function attachObservation(Story $story, string $sourceSlug = 'first-source'): void
    {
        $this->source($sourceSlug)->stories()->attach($story, [
            'source_url' => 'https://first.example/story',
        ]);
    }
}
