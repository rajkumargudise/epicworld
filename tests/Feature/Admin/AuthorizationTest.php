<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An authenticated user with no role must not automatically be able
 * to perform every editorial action - requirement #13 of the
 * Milestone 11 mandate. These pin the "ordinary authenticated user"
 * case; editor-vs-admin tier boundaries are covered alongside each
 * workflow's own tests.
 */
class AuthorizationTest extends TestCase
{
    use CreatesEditorialFixtures;
    use RefreshDatabase;

    public function test_an_authenticated_user_with_no_role_cannot_view_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => null]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_an_authenticated_user_with_no_role_cannot_view_the_story_queue(): void
    {
        $user = User::factory()->create(['role' => null]);

        $this->actingAs($user)->get('/admin/stories')->assertForbidden();
    }

    public function test_an_authenticated_user_with_no_role_cannot_view_a_story(): void
    {
        $user = User::factory()->create(['role' => null]);
        $story = $this->story();

        $this->actingAs($user)->get("/admin/stories/{$story->id}")->assertForbidden();
    }

    public function test_an_authenticated_user_with_no_role_cannot_view_the_article_editor(): void
    {
        $user = User::factory()->create(['role' => null]);
        $article = $this->article(['status' => ArticleStatus::Review]);

        $this->actingAs($user)->get("/admin/articles/{$article->id}/edit")->assertForbidden();
    }

    public function test_an_editor_cannot_publish(): void
    {
        $user = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Scheduled]);

        $this->actingAs($user)->post("/admin/articles/{$article->id}/publish")->assertForbidden();
    }

    public function test_an_editor_cannot_confirm_sensitive_review(): void
    {
        $user = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Review]);

        $this->actingAs($user)->post("/admin/articles/{$article->id}/confirm-sensitive-review")->assertForbidden();
    }
}
