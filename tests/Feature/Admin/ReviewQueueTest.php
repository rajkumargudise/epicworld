<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use CreatesEditorialFixtures;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/admin/review')->assertRedirect('/login');
    }

    public function test_the_queue_lists_only_articles_in_review(): void
    {
        $editor = User::factory()->editor()->create();
        $this->article(['title' => 'Waiting for review', 'status' => ArticleStatus::Review]);
        $this->article(['title' => 'Already a draft', 'status' => ArticleStatus::Draft]);

        $this->actingAs($editor)->get('/admin/review')
            ->assertOk()
            ->assertSee('Waiting for review')
            ->assertDontSee('Already a draft');
    }

    public function test_an_admin_can_approve_and_publish_selected_articles_in_one_action(): void
    {
        $admin = User::factory()->admin()->create();
        $a = $this->article(['status' => ArticleStatus::Review]);
        $b = $this->article(['status' => ArticleStatus::Review]);

        $this->actingAs($admin)->post('/admin/review/publish', ['ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(ArticleStatus::Published, $a->refresh()->status);
        $this->assertSame(ArticleStatus::Published, $b->refresh()->status);
        $this->assertNotNull($a->published_at);
    }

    public function test_an_article_that_fails_quality_checks_is_skipped_not_published(): void
    {
        $admin = User::factory()->admin()->create();
        $bad = $this->article(['status' => ArticleStatus::Review, 'content' => '']);

        $this->actingAs($admin)->post('/admin/review/publish', ['ids' => [$bad->id]])
            ->assertSessionHas('error');

        $this->assertSame(ArticleStatus::Review, $bad->refresh()->status);
    }

    public function test_an_editor_cannot_publish_through_the_queue(): void
    {
        $editor = User::factory()->editor()->create();
        $article = $this->article(['status' => ArticleStatus::Review]);

        $this->actingAs($editor)->post('/admin/review/publish', ['ids' => [$article->id]])->assertForbidden();

        $this->assertSame(ArticleStatus::Review, $article->refresh()->status);
    }
}
