<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\Topic;

/**
 * Small, explicit fixture builders in the same style as the rest of
 * this codebase's tests (direct Model::create() calls, no factories
 * for the editorial models - see PublicationDecisionTest) rather than
 * introducing new factory classes for this milestone alone.
 */
trait CreatesEditorialFixtures
{
    private function category(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'World',
            'slug' => 'world-'.uniqid(),
            'is_sensitive' => false,
        ], $overrides));
    }

    private function topic(array $overrides = []): Topic
    {
        return Topic::create(array_merge([
            'category_id' => $this->category()->id,
            'name' => 'General',
            'slug' => 'general-'.uniqid(),
            'is_sensitive' => false,
        ], $overrides));
    }

    /**
     * Defaults to a non-sensitive Topic (rather than none at all), so
     * a story built with no overrides is not implicitly "sensitive:
     * unclassified" per SensitiveContentRouter - tests that want the
     * sensitive path pass an explicit topic_id/no topic instead.
     */
    private function story(array $overrides = []): Story
    {
        $defaults = [
            'title' => 'A well-sourced story',
            'slug' => 'a-well-sourced-story-'.uniqid(),
            'content_hash' => hash('sha256', 'story-'.uniqid()),
            'status' => StoryStatus::Candidate,
            'importance' => 5,
            'first_seen_at' => now(),
            'facts' => [['source_id' => 1, 'source_name' => 'Example', 'reported' => ['title' => 'Reported fact']]],
        ];

        if (! array_key_exists('topic_id', $overrides)) {
            $defaults['topic_id'] = $this->topic()->id;
        }

        return Story::create(array_merge($defaults, $overrides));
    }

    private function article(array $overrides = [], array $storyOverrides = []): Article
    {
        $category = $this->category();
        $story = $this->story($storyOverrides);

        return Article::create(array_merge([
            'story_id' => $story->id,
            'category_id' => $category->id,
            'title' => 'A complete article',
            'slug' => 'a-complete-article-'.uniqid(),
            'content' => str_repeat('This is enough content to pass the minimum length check. ', 2),
            'status' => ArticleStatus::Draft,
        ], $overrides));
    }
}
