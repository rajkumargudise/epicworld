<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every status transition the CMS exposes goes through
 * PublicationPolicy, gated by ArticlePolicy - these tests exercise
 * that end to end over real HTTP requests, including the sensitive-
 * content block requirement #10-#12 describe.
 */
class PublicationWorkflowTest extends TestCase
{
    use CreatesEditorialFixtures;
    use RefreshDatabase;

    public function test_an_editor_can_approve_a_review_article_which_schedules_it(): void
    {
        $editor = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Review]);

        $response = $this->actingAs($editor)->post("/admin/articles/{$article->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);
    }

    public function test_approving_a_failing_article_surfaces_the_domain_services_error(): void
    {
        $editor = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Review, 'content' => '']);

        $response = $this->actingAs($editor)->post("/admin/articles/{$article->id}/approve");

        $response->assertSessionHas('error');
        $this->assertStringContainsString('quality checks', session('error'));
        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
    }

    public function test_an_admin_can_publish_a_scheduled_article(): void
    {
        $admin = User::factory()->admin()->create();
        $article = $this->article(['status' => ArticleStatus::Scheduled]);

        $response = $this->actingAs($admin)->post("/admin/articles/{$article->id}/publish");

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_a_sensitive_article_cannot_be_published_without_review_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $topic = $this->topic(['is_sensitive' => true]);
        $article = $this->article(['status' => ArticleStatus::Scheduled], ['topic_id' => $topic->id]);

        $response = $this->actingAs($admin)->post("/admin/articles/{$article->id}/publish");

        $response->assertSessionHas('error');
        $this->assertStringContainsString('sensitive content', session('error'));
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);
    }

    public function test_a_sensitive_article_cannot_be_approved_without_review_confirmation(): void
    {
        $editor = User::factory()->editor()->create();
        $topic = $this->topic(['is_sensitive' => true]);
        $article = $this->article(['status' => ArticleStatus::Review], ['topic_id' => $topic->id]);

        $response = $this->actingAs($editor)->post("/admin/articles/{$article->id}/approve");

        $response->assertSessionHas('error');
        $this->assertStringContainsString('sensitive content', session('error'));
        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
    }

    public function test_an_admin_confirming_sensitive_review_then_unblocks_approval_and_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $topic = $this->topic(['is_sensitive' => true]);
        $article = $this->article(['status' => ArticleStatus::Review], ['topic_id' => $topic->id]);

        $this->actingAs($admin)->post("/admin/articles/{$article->id}/confirm-sensitive-review")
            ->assertSessionHas('status');

        $this->actingAs($admin)->post("/admin/articles/{$article->id}/approve");
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);

        $this->actingAs($admin)->post("/admin/articles/{$article->id}/publish");
        $this->assertSame(ArticleStatus::Published, $article->refresh()->status);
    }

    public function test_a_guest_cannot_approve_an_article(): void
    {
        $article = $this->article(['status' => ArticleStatus::Review]);

        $this->post("/admin/articles/{$article->id}/approve")->assertRedirect('/login');
        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
    }
}
