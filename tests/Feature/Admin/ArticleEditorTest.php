<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleEditorTest extends TestCase
{
    use CreatesEditorialFixtures;
    use RefreshDatabase;

    public function test_the_editor_shows_the_articles_content_fields(): void
    {
        $user = User::factory()->editor()->create();
        $article = $this->article([
            'title' => 'Draft headline',
            'dek' => 'A short standfirst',
            'seo_title' => 'SEO headline',
            'editorial_metadata' => ['generation_method' => 'ai_assisted'],
        ]);

        $response = $this->actingAs($user)->get("/admin/articles/{$article->id}/edit");

        $response->assertOk();
        $response->assertSee('Draft headline');
        $response->assertSee('A short standfirst', escape: false);
        $response->assertSee('SEO headline');
        $response->assertSee('AI-generated draft');
    }

    public function test_editing_an_article_persists_content_fields_and_records_who_edited_it(): void
    {
        $user = User::factory()->editor()->create(['name' => 'Jamie Editor']);
        $article = $this->article();

        $response = $this->actingAs($user)->put("/admin/articles/{$article->id}", [
            'title' => 'Updated headline',
            'dek' => 'Updated dek',
            'excerpt' => 'Updated excerpt',
            'content' => str_repeat('Updated content that is long enough. ', 2),
            'category_id' => $article->category_id,
            'seo_title' => 'Updated SEO title',
            'seo_description' => 'Updated SEO description',
            'tags' => 'breaking, world',
        ]);

        $response->assertRedirect(route('admin.articles.edit', $article));

        $article->refresh();
        $this->assertSame('Updated headline', $article->title);
        $this->assertSame('Updated dek', $article->dek);
        $this->assertSame('Updated SEO title', $article->seo_title);
        $this->assertSame('Jamie Editor', $article->editorial_metadata['human_edited_by']);
        $this->assertArrayHasKey('human_edited_at', $article->editorial_metadata);
        $this->assertEqualsCanonicalizing(['breaking', 'world'], $article->tags->pluck('name')->all());
    }

    public function test_the_update_endpoint_ignores_a_status_field_in_the_request(): void
    {
        $user = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Draft]);

        $this->actingAs($user)->put("/admin/articles/{$article->id}", [
            'title' => $article->title,
            'content' => $article->content,
            'status' => 'published',
        ]);

        $this->assertSame(ArticleStatus::Draft, $article->refresh()->status);
    }

    public function test_the_update_endpoint_ignores_published_at_and_other_privileged_fields(): void
    {
        $user = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Draft]);

        $this->actingAs($user)->put("/admin/articles/{$article->id}", [
            'title' => $article->title,
            'content' => $article->content,
            'published_at' => now()->toIso8601String(),
        ]);

        $this->assertNull($article->refresh()->published_at);
    }

    public function test_a_guest_cannot_reach_the_article_editor(): void
    {
        $article = $this->article();

        $this->get("/admin/articles/{$article->id}/edit")->assertRedirect('/login');
    }
}
