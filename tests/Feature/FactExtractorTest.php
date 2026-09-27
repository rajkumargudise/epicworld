<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\Story;
use App\Services\Editorial\FactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FactExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_fact_sheet_from_a_single_source_observation(): void
    {
        $story = $this->story();
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source']);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/a',
            'title' => 'Reported headline',
            'summary' => 'Reported summary',
            'published_at' => Carbon::parse('2026-09-20 10:00:00'),
            'discovered_at' => now(),
        ]);

        $changed = app(FactExtractor::class)->extract($story);

        $this->assertTrue($changed);
        $facts = $story->refresh()->facts;
        $this->assertCount(1, $facts);
        $this->assertSame($source->id, $facts[0]['source_id']);
        $this->assertSame('Example Source', $facts[0]['source_name']);
        $this->assertSame('Reported headline', $facts[0]['reported']['title']);
        $this->assertSame('Reported summary', $facts[0]['reported']['summary']);
        $this->assertSame('https://example.com/a', $facts[0]['reported']['source_url']);
        $this->assertArrayHasKey('published_at', $facts[0]['reported']);
    }

    public function test_it_combines_multiple_source_observations_in_discovery_order(): void
    {
        $story = $this->story();
        $first = Source::create(['name' => 'First Source', 'slug' => 'first-source']);
        $second = Source::create(['name' => 'Second Source', 'slug' => 'second-source']);
        $first->stories()->attach($story, [
            'source_url' => 'https://a.example.com',
            'title' => 'First headline',
            'discovered_at' => now()->subHour(),
        ]);
        $second->stories()->attach($story, [
            'source_url' => 'https://b.example.com',
            'title' => 'Second headline',
            'discovered_at' => now(),
        ]);

        app(FactExtractor::class)->extract($story);

        $facts = $story->refresh()->facts;
        $this->assertCount(2, $facts);
        $this->assertSame($first->id, $facts[0]['source_id']);
        $this->assertSame($second->id, $facts[1]['source_id']);
    }

    public function test_it_omits_fields_the_source_did_not_report(): void
    {
        $story = $this->story();
        $source = Source::create(['name' => 'Sparse Source', 'slug' => 'sparse-source']);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/sparse',
            'discovered_at' => now(),
        ]);

        app(FactExtractor::class)->extract($story);

        $reported = $story->refresh()->facts[0]['reported'];
        $this->assertSame(['source_url' => 'https://example.com/sparse'], $reported);
    }

    public function test_re_extracting_with_unchanged_data_reports_no_change(): void
    {
        $story = $this->story();
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source']);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/a',
            'title' => 'Reported headline',
            'discovered_at' => now(),
        ]);
        $extractor = app(FactExtractor::class);
        $extractor->extract($story);

        $changed = $extractor->extract($story->refresh());

        $this->assertFalse($changed);
    }

    public function test_a_corrected_source_field_replaces_the_prior_value_rather_than_duplicating(): void
    {
        $story = $this->story();
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source']);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/a',
            'title' => 'Original headline',
            'discovered_at' => now(),
        ]);
        $extractor = app(FactExtractor::class);
        $extractor->extract($story);

        $story->sources()->updateExistingPivot($source->id, ['title' => 'Corrected headline']);
        $changed = $extractor->extract($story->refresh());

        $this->assertTrue($changed);
        $facts = $story->refresh()->facts;
        $this->assertCount(1, $facts);
        $this->assertSame('Corrected headline', $facts[0]['reported']['title']);
    }

    public function test_a_story_with_no_source_observations_gets_an_empty_fact_sheet(): void
    {
        $story = $this->story();

        $changed = app(FactExtractor::class)->extract($story);

        $this->assertFalse($changed);
        $this->assertSame([], $story->refresh()->facts ?? []);
    }

    private function story(): Story
    {
        return Story::create([
            'title' => 'A story',
            'slug' => 'a-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-story-'.uniqid()),
        ]);
    }
}
