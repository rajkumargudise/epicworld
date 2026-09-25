<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\Story;
use App\Services\Stories\StoryMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_canonical_url_matches_a_story(): void
    {
        $source = $this->source('first-source');
        $story = $this->story('https://example.com/story');

        $result = app(StoryMatcher::class)->match($source, 'https://example.com/story', null);

        $this->assertTrue($result->matched);
        $this->assertTrue($result->story->is($story));
        $this->assertSame('canonical_url', $result->reason);
    }

    public function test_canonical_url_matching_applies_url_normalization(): void
    {
        $source = $this->source('first-source');
        $story = $this->story('https://example.com/story');

        $result = app(StoryMatcher::class)->match(
            $source,
            'HTTPS://EXAMPLE.COM:443/story#section',
            null
        );

        $this->assertTrue($result->matched);
        $this->assertTrue($result->story->is($story));
        $this->assertSame('canonical_url', $result->reason);
    }

    public function test_url_mismatch_does_not_match_a_story(): void
    {
        $source = $this->source('first-source');
        $this->story('https://example.com/story');

        $result = app(StoryMatcher::class)->match($source, 'https://example.com/other-story', null);

        $this->assertFalse($result->matched);
        $this->assertNull($result->story);
    }

    public function test_same_source_external_id_matches_a_story(): void
    {
        $source = $this->source('first-source');
        $story = $this->story();
        $source->stories()->attach($story, ['external_id' => 'source-123']);

        $result = app(StoryMatcher::class)->match($source, null, 'source-123');

        $this->assertTrue($result->matched);
        $this->assertTrue($result->story->is($story));
        $this->assertSame('source_external_id', $result->reason);
    }

    public function test_same_external_id_from_a_different_source_does_not_match(): void
    {
        $firstSource = $this->source('first-source');
        $secondSource = $this->source('second-source');
        $story = $this->story();
        $firstSource->stories()->attach($story, ['external_id' => 'source-123']);

        $result = app(StoryMatcher::class)->match($secondSource, null, 'source-123');

        $this->assertFalse($result->matched);
        $this->assertNull($result->story);
    }

    public function test_title_similarity_is_not_a_match(): void
    {
        $source = $this->source('first-source');
        $this->story(null, 'Same headline');

        $result = app(StoryMatcher::class)->match($source, null, null);

        $this->assertFalse($result->matched);
    }

    public function test_url_normalization_is_conservative_and_deterministic(): void
    {
        $matcher = app(StoryMatcher::class);

        $this->assertSame(
            'https://example.com/story?ref=feed',
            $matcher->normalizeUrl(' HTTPS://EXAMPLE.COM:443/story?ref=feed#section ')
        );
        $this->assertSame(
            'https://example.com/story/',
            $matcher->normalizeUrl('https://example.com/story/')
        );
        $this->assertNotSame(
            $matcher->normalizeUrl('https://example.com/story?ref=one'),
            $matcher->normalizeUrl('https://example.com/story?ref=two')
        );
    }

    private function source(string $slug): Source
    {
        return Source::create([
            'name' => $slug,
            'slug' => $slug,
        ]);
    }

    private function story(?string $canonicalUrl = null, string $title = 'Story title'): Story
    {
        return Story::create([
            'title' => $title,
            'slug' => str_replace(' ', '-', strtolower($title)),
            'canonical_url' => $canonicalUrl,
        ]);
    }
}
