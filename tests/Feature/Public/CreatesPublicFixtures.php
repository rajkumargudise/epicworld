<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;

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
            'content' => '<p>'.str_repeat('This is the published article body. ', 20).'</p>',
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
}
