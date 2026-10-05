<?php

namespace Tests\Feature\Community;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributorFlowTest extends TestCase
{
    use RefreshDatabase;

    private function contributor(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => User::ROLE_CONTRIBUTOR], $attrs));
    }

    private function category(): Category
    {
        return Category::firstOrCreate(["slug" => "technology"], ["name" => "Technology", "is_active" => true, "sort_order" => 1]);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'title' => 'How small teams ship faster software',
            'dek' => 'A practical look at shipping.',
            'category_id' => $this->category()->id,
            'content' => "Intro paragraph about shipping.\n\n## Keep scope small\n\n".str_repeat('Small batches reduce risk and speed learning. ', 20),
            'action' => 'submit',
        ], $over);
    }

    public function test_anyone_can_register_but_only_as_a_contributor(): void
    {
        $this->post('/register', [
            'name' => 'Asha Reader',
            'email' => 'asha@example.com',
            'password' => 'GoodPassw0rd!',
            'password_confirmation' => 'GoodPassw0rd!',
            'accept_terms' => '1',
            'role' => 'admin', // must be ignored
        ])->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_CONTRIBUTOR, $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_weak_passwords_duplicates_and_honeypot_bots(): void
    {
        $this->post('/register', ['name' => 'A B', 'email' => 'weak@example.com', 'password' => 'short', 'password_confirmation' => 'short', 'accept_terms' => '1'])
            ->assertSessionHasErrors('password');

        User::factory()->create(['email' => 'taken@example.com']);
        $this->post('/register', ['name' => 'A B', 'email' => 'taken@example.com', 'password' => 'GoodPassw0rd!', 'password_confirmation' => 'GoodPassw0rd!', 'accept_terms' => '1'])
            ->assertSessionHasErrors('email');

        $this->post('/register', ['name' => 'Bot Name', 'email' => 'bot@example.com', 'password' => 'GoodPassw0rd!', 'password_confirmation' => 'GoodPassw0rd!', 'accept_terms' => '1', 'company_website' => 'http://spam'])
            ->assertRedirect(route('home'));
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_a_contributor_can_never_open_the_cms(): void
    {
        $user = $this->contributor();

        foreach (['/admin', '/admin/review', '/admin/comments', '/admin/settings', '/admin/users', '/admin/launch', '/admin/messages', '/admin/stories'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $this->actingAs($user)->post('/admin/review/publish', ['ids' => [1]])->assertForbidden();
    }

    public function test_login_sends_each_role_to_the_right_place_and_blocks_suspended_users(): void
    {
        $this->contributor(['email' => 'c@example.com', 'password' => 'Passw0rd-ok-123']);
        $this->post('/login', ['email' => 'c@example.com', 'password' => 'Passw0rd-ok-123'])->assertRedirect(route('account.dashboard'));
        $this->post('/logout');

        User::factory()->editor()->create(['email' => 'e@example.com', 'password' => 'Passw0rd-ok-123']);
        $this->post('/login', ['email' => 'e@example.com', 'password' => 'Passw0rd-ok-123'])->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        $this->contributor(['email' => 's@example.com', 'password' => 'Passw0rd-ok-123', 'suspended_at' => now()]);
        $this->post('/login', ['email' => 's@example.com', 'password' => 'Passw0rd-ok-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_submitting_a_post_puts_it_in_review_and_it_is_not_public(): void
    {
        $user = $this->contributor();

        $this->actingAs($user)->post('/account/posts', $this->payload())->assertRedirect(route('account.dashboard'));

        $article = Article::firstOrFail();
        $this->assertSame(ArticleStatus::Review, $article->status);
        $this->assertSame($user->id, $article->author_id);
        $this->assertTrue($article->isContributed());
        $this->get(route('article.show', $article))->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee($article->slug, false);
    }

    public function test_saving_a_draft_keeps_it_editable_but_a_submitted_post_is_locked(): void
    {
        $user = $this->contributor();
        $this->actingAs($user)->post('/account/posts', $this->payload(['action' => 'draft']));
        $draft = Article::firstOrFail();
        $this->assertSame(ArticleStatus::Draft, $draft->status);

        $this->actingAs($user)->get("/account/posts/{$draft->id}/edit")->assertOk();
        $this->actingAs($user)->put("/account/posts/{$draft->id}", $this->payload(['action' => 'submit', 'title' => 'An updated and better title']))->assertRedirect();
        $this->assertSame(ArticleStatus::Review, $draft->refresh()->status);

        // Now locked.
        $this->actingAs($user)->get("/account/posts/{$draft->id}/edit")->assertNotFound();
        $this->actingAs($user)->put("/account/posts/{$draft->id}", $this->payload())->assertNotFound();
    }

    public function test_a_contributor_cannot_touch_someone_elses_post_or_set_status(): void
    {
        $owner = $this->contributor();
        $this->actingAs($owner)->post('/account/posts', $this->payload(['action' => 'draft']));
        $post = Article::firstOrFail();

        $other = $this->contributor();
        $this->actingAs($other)->get("/account/posts/{$post->id}/edit")->assertNotFound();
        $this->actingAs($other)->delete("/account/posts/{$post->id}")->assertNotFound();

        // A forged "status" field is ignored.
        $this->actingAs($owner)->put("/account/posts/{$post->id}", $this->payload(['action' => 'draft', 'status' => 'published']));
        $this->assertSame(ArticleStatus::Draft, $post->refresh()->status);
    }

    public function test_submission_validation_and_the_review_cap(): void
    {
        $user = $this->contributor();

        $this->actingAs($user)->post('/account/posts', $this->payload(['content' => 'too short']))->assertSessionHasErrors('content');
        $this->actingAs($user)->post('/account/posts', $this->payload(['title' => '<script>alert(1)</script> bad']))->assertSessionHasErrors('title');

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)->post('/account/posts', $this->payload(['title' => "A reasonable title number {$i}"]));
        }
        $this->actingAs($user)->post('/account/posts', $this->payload(['title' => 'A sixth submission title']))->assertSessionHas('error');
        $this->assertSame(5, Article::count());
    }

    public function test_an_admin_can_approve_and_publish_a_contributor_post_and_it_is_labelled(): void
    {
        $user = $this->contributor(['name' => 'Asha Reader']);
        $this->actingAs($user)->post('/account/posts', $this->payload());
        $article = Article::firstOrFail();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/review')->assertOk()->assertSee('Community post by Asha Reader');
        $this->actingAs($admin)->post('/admin/review/publish', ['ids' => [$article->id]])->assertSessionHas('status');

        $this->assertSame(ArticleStatus::Published, $article->refresh()->status);
        $this->get(route('article.show', $article))->assertOk()->assertSee('Community contributor')->assertSee('Asha Reader');
    }

    public function test_an_editor_can_send_a_post_back_with_feedback(): void
    {
        $user = $this->contributor();
        $this->actingAs($user)->post('/account/posts', $this->payload());
        $article = Article::firstOrFail();

        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->post("/admin/review/{$article->id}/reject", ['reason' => 'Please add sources for the claims.'])->assertSessionHas('status');

        $article->refresh();
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Please add sources for the claims.')->assertSee('Needs changes');
    }

    public function test_markup_in_a_contributor_post_cannot_inject_html(): void
    {
        $user = $this->contributor();
        $this->actingAs($user)->post('/account/posts', $this->payload(['content' => "Lead <script>alert(1)</script> text.\n\n## Heading\n\n".str_repeat('Safe body [x](javascript:alert(1)) text here. ', 20)]));
        $article = Article::firstOrFail();
        $article->update(['status' => ArticleStatus::Published, 'published_at' => now()->subMinute()]);

        $html = $this->get(route('article.show', $article))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_admins_manage_users_but_never_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->contributor();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", ['role' => 'editor'])->assertSessionHas('status');
        $this->assertSame('editor', $user->refresh()->role);

        $this->actingAs($admin)->put("/admin/users/{$user->id}", ['suspend' => 1]);
        $this->assertTrue($user->refresh()->isSuspended());

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['suspend' => 1])->assertSessionHas('error');
        $this->assertFalse($admin->refresh()->isSuspended());

        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    }
}
