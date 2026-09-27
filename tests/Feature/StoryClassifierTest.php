<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Source;
use App\Models\Story;
use App\Models\Topic;
use App\Services\Editorial\StoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_the_topic_from_the_only_sources_default_topic(): void
    {
        $topic = $this->topic('Technology');
        $story = $this->story();
        $this->attachSource($story, $topic, discoveredAt: now());

        $classified = app(StoryClassifier::class)->classify($story);

        $this->assertTrue($classified);
        $this->assertSame($topic->id, $story->refresh()->topic_id);
    }

    public function test_it_does_nothing_when_no_source_has_a_default_topic(): void
    {
        $story = $this->story();
        $this->attachSource($story, topic: null, discoveredAt: now());

        $classified = app(StoryClassifier::class)->classify($story);

        $this->assertFalse($classified);
        $this->assertNull($story->refresh()->topic_id);
    }

    public function test_it_never_overwrites_an_existing_topic(): void
    {
        $existing = $this->topic('Existing');
        $conflicting = $this->topic('Conflicting');
        $story = $this->story(['topic_id' => $existing->id]);
        $this->attachSource($story, $conflicting, discoveredAt: now());

        $classified = app(StoryClassifier::class)->classify($story);

        $this->assertFalse($classified);
        $this->assertSame($existing->id, $story->refresh()->topic_id);
    }

    public function test_it_prefers_the_earliest_discovered_source_when_sources_disagree(): void
    {
        $earlier = $this->topic('Earlier Topic');
        $later = $this->topic('Later Topic');
        $story = $this->story();
        $this->attachSource($story, $later, discoveredAt: now(), sourceName: 'later-source');
        $this->attachSource($story, $earlier, discoveredAt: now()->subHour(), sourceName: 'earlier-source');

        $classified = app(StoryClassifier::class)->classify($story);

        $this->assertTrue($classified);
        $this->assertSame($earlier->id, $story->refresh()->topic_id);
    }

    public function test_a_source_without_a_default_topic_is_skipped_in_favor_of_one_that_has_one(): void
    {
        $topic = $this->topic('Has Topic');
        $story = $this->story();
        // Attached first (earlier discovered_at) but has no default topic.
        $this->attachSource($story, null, discoveredAt: now()->subHour(), sourceName: 'no-topic-source');
        $this->attachSource($story, $topic, discoveredAt: now(), sourceName: 'has-topic-source');

        $classified = app(StoryClassifier::class)->classify($story);

        $this->assertTrue($classified);
        $this->assertSame($topic->id, $story->refresh()->topic_id);
    }

    private function topic(string $name): Topic
    {
        $category = Category::create(['name' => 'Cat-'.uniqid(), 'slug' => 'cat-'.uniqid()]);

        return Topic::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => 'topic-'.uniqid(),
        ]);
    }

    private function story(array $overrides = []): Story
    {
        return Story::create(array_merge([
            'title' => 'A story',
            'slug' => 'a-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-story-'.uniqid()),
        ], $overrides));
    }

    private function attachSource(Story $story, ?Topic $topic, $discoveredAt, ?string $sourceName = null): Source
    {
        $sourceName ??= 'source-'.uniqid();

        $source = Source::create([
            'name' => $sourceName,
            'slug' => $sourceName.'-'.uniqid(),
            'default_topic_id' => $topic?->id,
        ]);

        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/'.uniqid(),
            'discovered_at' => $discoveredAt,
        ]);

        return $source;
    }
}
