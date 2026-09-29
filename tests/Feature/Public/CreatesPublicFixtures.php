<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\Tag;
use App\Models\Topic;

/**
 * Direct Model::create() fixtures, matching the rest of this codebase's
 * test style (see Admin's CreatesEditorialFixtures) rather than
 * introducing factories for models that don't have them.
 */
trait CreatesPublicFixtures
{
    private function category(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Technology',
            'slug' => 'technology-'.uniqid(),
            'is_active' => true,
            'sort_order' => 10,
        ], $overrides));
    }

    /**
     * Defaults to a fully publicly-visible article. Pass
     * ['status' => ArticleStatus::Draft] etc. to build the negative
     * cases.
     */
    private function publishedArticle(array $overrides = []): Article
    {
        $category = $overrides['category_id'] ?? null ? null : $this->category();

        return Article::create(array_merge([
            'category_id' => $category?->id,
            'title' => 'A published article',
            'slug' => 'a-published-article-'.uniqid(),
            'dek' => 'A short standfirst for this article.',
            'content' => str_repeat('This is the published article body. ', 20),
            'excerpt' => 'A short excerpt.',
            'status' => ArticleStatus::Published,
            'published_at' => now()->subHour(),
            'allow_indexing' => true,
        ], $overrides));
    }

    private function tag(array $overrides = []): Tag
    {
        return Tag::create(array_merge([
            'name' => 'Cloud',
            'slug' => 'cloud-'.uniqid(),
        ], $overrides));
    }

    private function topic(array $overrides = []): Topic
    {
        return Topic::create(array_merge([
            'category_id' => $this->category()->id,
            'name' => 'General',
            'slug' => 'general-'.uniqid(),
            'is_active' => true,
        ], $overrides));
    }

    /**
     * A published article whose Story carries the given Topic - the
     * only way an Article is associated with a Topic at all, since
     * Article itself has no topic_id.
     */
    private function publishedArticleWithTopic(Topic $topic, array $overrides = []): Article
    {
        $story = Story::create([
            'topic_id' => $topic->id,
            'title' => 'Story for '.($overrides['title'] ?? 'a published article'),
            'slug' => 'story-'.uniqid(),
            'content_hash' => hash('sha256', uniqid()),
            'status' => StoryStatus::Published,
            'facts' => [],
        ]);

        return $this->publishedArticle(array_merge(['story_id' => $story->id], $overrides));
    }
}
