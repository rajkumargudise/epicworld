<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Support\CanonicalUrl;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryCanonicalUrlHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_story_computes_its_canonical_url_hash(): void
    {
        $story = $this->story('https://example.com/story');

        $this->assertSame(
            CanonicalUrl::hash('https://example.com/story'),
            $story->canonical_url_hash
        );
    }

    public function test_hash_reflects_normalization_not_the_raw_url(): void
    {
        $story = $this->story('HTTPS://EXAMPLE.COM:443/story#section');

        $this->assertSame(
            CanonicalUrl::hash('https://example.com/story'),
            $story->canonical_url_hash
        );
    }

    public function test_story_without_canonical_url_has_no_hash(): void
    {
        $story = $this->story(null);

        $this->assertNull($story->canonical_url_hash);
    }

    public function test_multiple_stories_without_a_canonical_url_are_allowed(): void
    {
        $this->story(null);
        $this->story(null);

        $this->assertDatabaseCount('stories', 2);
    }

    public function test_updating_canonical_url_recomputes_the_hash(): void
    {
        $story = $this->story('https://example.com/story-one');

        $story->canonical_url = 'https://example.com/story-two';
        $story->save();

        $this->assertSame(
            CanonicalUrl::hash('https://example.com/story-two'),
            $story->refresh()->canonical_url_hash
        );
    }

    public function test_duplicate_canonical_url_hash_is_rejected_at_the_database_level(): void
    {
        $this->story('https://example.com/story');

        $this->expectException(QueryException::class);

        $this->story('https://example.com/story');
    }

    private function story(?string $canonicalUrl, string $title = 'Story title'): Story
    {
        return Story::create([
            'title' => $title,
            'slug' => $title.'-'.uniqid(),
            'canonical_url' => $canonicalUrl,
        ]);
    }
}
