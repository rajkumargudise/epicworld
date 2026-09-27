<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\Story;
use App\Services\Editorial\FactExtractor;
use App\Services\Evidence\FactSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryFactSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_extracted_facts_as_a_fact_sheet(): void
    {
        $story = Story::create([
            'title' => 'A story',
            'slug' => 'a-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-story-'.uniqid()),
        ]);
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source-'.uniqid()]);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/a',
            'title' => 'The bridge reopens tomorrow',
            'discovered_at' => now(),
        ]);
        app(FactExtractor::class)->extract($story);

        $sheet = $story->refresh()->factSheet();

        $this->assertInstanceOf(FactSheet::class, $sheet);
        $this->assertSame(1, $sheet->count());
        $this->assertTrue($sheet->supports('the bridge reopens tomorrow'));
        $this->assertFalse($sheet->supports('the mayor resigned'));
    }

    public function test_a_story_with_no_facts_yields_an_empty_fact_sheet(): void
    {
        $story = Story::create([
            'title' => 'No facts',
            'slug' => 'no-facts-'.uniqid(),
            'content_hash' => hash('sha256', 'no-facts-'.uniqid()),
        ]);

        $sheet = $story->factSheet();

        $this->assertTrue($sheet->isEmpty());
    }
}
