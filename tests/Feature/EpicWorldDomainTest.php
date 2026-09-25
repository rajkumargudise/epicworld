<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\AutomationRunStatus;
use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\Article;
use App\Models\AutomationRun;
use App\Models\Category;
use App\Models\EditorialJob;
use App\Models\Media;
use App\Models\Source;
use App\Models\Story;
use App\Models\Tag;
use App\Models\Topic;
use Database\Seeders\EpicWorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpicWorldDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_has_topics(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
        $topic = Topic::create([
            'category_id' => $category->id,
            'name' => 'Software',
            'slug' => 'software',
        ]);

        $this->assertTrue($category->topics->contains($topic));
    }

    public function test_category_has_articles(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
        $article = Article::create([
            'category_id' => $category->id,
            'title' => 'Technology article',
            'slug' => 'technology-article',
        ]);

        $this->assertTrue($category->articles->contains($article));
    }

    public function test_topic_has_stories(): void
    {
        $topic = Topic::create([
            'name' => 'Software',
            'slug' => 'software',
        ]);
        $story = Story::create([
            'topic_id' => $topic->id,
            'title' => 'Software story',
            'slug' => 'software-story',
        ]);

        $this->assertTrue($topic->stories->contains($story));
    }

    public function test_story_has_sources(): void
    {
        $story = Story::create([
            'title' => 'Source-backed story',
            'slug' => 'source-backed-story',
        ]);
        $source = Source::create([
            'name' => 'Example Source',
            'slug' => 'example-source',
        ]);
        $story->sources()->attach($source);

        $this->assertTrue($story->sources->contains($source));
    }

    public function test_story_has_article(): void
    {
        $story = Story::create([
            'title' => 'Article-backed story',
            'slug' => 'article-backed-story',
        ]);
        $article = Article::create([
            'story_id' => $story->id,
            'title' => 'Story article',
            'slug' => 'story-article',
        ]);

        $this->assertTrue($story->article->is($article));
    }

    public function test_article_belongs_to_category(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
        $article = Article::create([
            'category_id' => $category->id,
            'title' => 'Categorized article',
            'slug' => 'categorized-article',
        ]);

        $this->assertTrue($article->category->is($category));
    }

    public function test_article_has_tags(): void
    {
        $article = Article::create([
            'title' => 'Tagged article',
            'slug' => 'tagged-article',
        ]);
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
        $article->tags()->attach($tag);

        $this->assertTrue($article->tags->contains($tag));
    }

    public function test_article_has_media(): void
    {
        $article = Article::create([
            'title' => 'Article with media',
            'slug' => 'article-with-media',
        ]);
        $media = Media::create([
            'article_id' => $article->id,
            'path' => 'articles/article-image.jpg',
        ]);

        $this->assertTrue($article->media->contains($media));
    }

    public function test_editorial_job_belongs_to_story(): void
    {
        $story = Story::create([
            'title' => 'Editorial story',
            'slug' => 'editorial-story',
        ]);
        $job = EditorialJob::create([
            'story_id' => $story->id,
            'job_type' => 'summarize',
        ]);

        $this->assertTrue($job->story->is($story));
    }

    public function test_editorial_job_belongs_to_article(): void
    {
        $article = Article::create([
            'title' => 'Editorial article',
            'slug' => 'editorial-article',
        ]);
        $job = EditorialJob::create([
            'article_id' => $article->id,
            'job_type' => 'review',
        ]);

        $this->assertTrue($job->article->is($article));
    }

    public function test_article_status_is_cast_to_enum(): void
    {
        $article = new Article(['status' => 'draft']);

        $this->assertInstanceOf(ArticleStatus::class, $article->status);
        $this->assertSame('draft', $article->status->value);
    }

    public function test_story_status_is_cast_to_enum(): void
    {
        $story = new Story(['status' => 'discovered']);

        $this->assertInstanceOf(StoryStatus::class, $story->status);
        $this->assertSame('discovered', $story->status->value);
    }

    public function test_editorial_job_status_is_cast_to_enum(): void
    {
        $job = new EditorialJob(['status' => 'pending']);

        $this->assertInstanceOf(EditorialJobStatus::class, $job->status);
        $this->assertSame('pending', $job->status->value);
    }

    public function test_automation_run_status_is_cast_to_enum(): void
    {
        $run = new AutomationRun(['status' => 'running']);

        $this->assertInstanceOf(AutomationRunStatus::class, $run->status);
        $this->assertSame('running', $run->status->value);
    }

    public function test_seeded_taxonomy_has_expected_integrity(): void
    {
        $this->seed(EpicWorldSeeder::class);

        $technology = Category::where('slug', 'technology')->firstOrFail();

        $this->assertSame(15, Category::count());
        $this->assertSame('Technology', $technology->name);
        $this->assertSame(
            [
                'software',
                'hardware',
                'gadgets',
                'cloud',
                'developer-tools',
                'platforms',
            ],
            $technology->topics->pluck('slug')->all()
        );
        $this->assertSame(
            Category::count(),
            Category::query()->select('slug')->distinct()->count()
        );
        $this->assertSame(
            Topic::count(),
            Topic::query()->select('slug')->distinct()->count()
        );
    }
}
