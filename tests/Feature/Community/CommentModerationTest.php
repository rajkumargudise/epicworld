<?php

namespace Tests\Feature\Community;

use App\Models\Comment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Feature\Public\CreatesPublicFixtures;
use Tests\TestCase;

class CommentModerationTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The per-IP limiter (3/min) is covered separately; these tests make many requests.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function token(int $secondsAgo = 10): string
    {
        return Crypt::encryptString((string) (now()->timestamp - $secondsAgo));
    }

    private function unusedHelper(array $over = [], $article = null)
    {
        $article ??= $this->publishedArticle();

        return [$article, $this->post(route('comments.store', $article), array_merge([
            'ct' => $this->token(),
            'author_name' => 'Reader One',
            'author_email' => 'reader@example.com',
            'body' => 'Great article, thanks for explaining this.',
        ], $over))];
    }

    public function test_a_new_comment_is_pending_and_never_visible_until_approved(): void
    {
        $article = $this->publishedArticle();

        $this->from('/')->post(route('comments.store', $article), [
            'ct' => $this->token(), 'author_name' => 'Reader One', 'author_email' => 'reader@example.com', 'body' => 'Great article, thanks for explaining this.',
        ])->assertRedirect(route('article.show', $article).'#comments')->assertSessionHas('comment_status');

        $comment = Comment::firstOrFail();
        $this->assertSame(Comment::PENDING, $comment->status);

        $this->get(route('article.show', $article))->assertOk()->assertDontSee('Great article, thanks')->assertDontSee('reader@example.com');

        $comment->update(['status' => Comment::APPROVED, 'approved_at' => now()]);
        $this->get(route('article.show', $article))->assertSee('Great article, thanks')->assertDontSee('reader@example.com');
    }

    public function test_rejected_and_spam_comments_stay_hidden(): void
    {
        $article = $this->publishedArticle();
        foreach ([Comment::REJECTED, Comment::SPAM, Comment::PENDING] as $status) {
            Comment::create(['article_id' => $article->id, 'author_name' => 'X', 'author_email' => 'x@example.com', 'body' => "Hidden {$status} comment", 'status' => $status]);
        }

        $this->get(route('article.show', $article))->assertDontSee('Hidden rejected')->assertDontSee('Hidden spam')->assertDontSee('Hidden pending');
    }

    public function test_spam_defences(): void
    {
        $article = $this->publishedArticle();
        $base = ['ct' => $this->token(), 'author_name' => 'Reader', 'author_email' => 'r@example.com', 'body' => 'A perfectly fine comment here.'];

        // honeypot: silently accepted, nothing stored
        $this->post(route('comments.store', $article), $base + ['website' => 'http://spam.example'])->assertSessionHas('comment_status');
        // too fast
        $this->post(route('comments.store', $article), array_merge($base, ['ct' => $this->token(0)]))->assertSessionHas('comment_error');
        // forged token
        $this->post(route('comments.store', $article), array_merge($base, ['ct' => 'forged']))->assertSessionHas('comment_error');
        // link stuffing
        $this->post(route('comments.store', $article), array_merge($base, ['body' => 'buy http://a.com http://b.com http://c.com now']))->assertSessionHas('comment_error');
        // invalid email / markup in name
        $this->post(route('comments.store', $article), array_merge($base, ['author_email' => 'nope']))->assertSessionHasErrors('author_email');
        $this->post(route('comments.store', $article), array_merge($base, ['author_name' => '<b>x</b>']))->assertSessionHasErrors('author_name');

        $this->assertSame(0, Comment::count());

        // duplicates are suppressed
        $this->post(route('comments.store', $article), $base);
        $this->post(route('comments.store', $article), $base);
        $this->assertSame(1, Comment::count());
    }

    public function test_comment_text_is_escaped_when_shown(): void
    {
        $article = $this->publishedArticle();
        Comment::create(['article_id' => $article->id, 'author_name' => 'Eve', 'author_email' => 'e@example.com', 'body' => '<script>alert(1)</script> hi', 'status' => Comment::APPROVED, 'approved_at' => now()]);

        $html = $this->get(route('article.show', $article))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_comments_can_be_switched_off(): void
    {
        $article = $this->publishedArticle();
        Setting::write('comments_enabled', '0');

        $this->get(route('article.show', $article))->assertDontSee('id="comments"', false);
        $this->post(route('comments.store', $article), ['ct' => $this->token(), 'author_name' => 'R', 'author_email' => 'r@example.com', 'body' => 'hello there'])->assertNotFound();
    }

    public function test_an_editors_own_comment_is_approved_automatically(): void
    {
        $article = $this->publishedArticle();
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post(route('comments.store', $article), ['ct' => $this->token(), 'author_name' => 'Ed', 'author_email' => 'ed@example.com', 'body' => 'Staff reply here.']);

        $this->assertSame(Comment::APPROVED, Comment::firstOrFail()->status);
    }

    public function test_moderators_can_approve_reject_spam_and_delete_in_bulk(): void
    {
        $article = $this->publishedArticle();
        $ids = collect(range(1, 4))->map(fn ($i) => Comment::create(['article_id' => $article->id, 'author_name' => "U{$i}", 'author_email' => "u{$i}@example.com", 'body' => "Comment number {$i}", 'status' => 'pending'])->id);
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin/comments')->assertOk()->assertSee('Comment number 1');
        $this->actingAs($editor)->put('/admin/comments', ['ids' => [$ids[0]], 'action' => 'approve']);
        $this->actingAs($editor)->put('/admin/comments', ['ids' => [$ids[1]], 'action' => 'reject']);
        $this->actingAs($editor)->put('/admin/comments', ['ids' => [$ids[2]], 'action' => 'spam']);
        $this->actingAs($editor)->put('/admin/comments', ['ids' => [$ids[3]], 'action' => 'delete']);

        $this->assertSame(Comment::APPROVED, Comment::find($ids[0])->status);
        $this->assertNotNull(Comment::find($ids[0])->approved_at);
        $this->assertSame(Comment::REJECTED, Comment::find($ids[1])->status);
        $this->assertSame(Comment::SPAM, Comment::find($ids[2])->status);
        $this->assertNull(Comment::find($ids[3]));
    }

    public function test_guests_and_contributors_cannot_moderate(): void
    {
        $this->get('/admin/comments')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]))->put('/admin/comments', ['ids' => [1], 'action' => 'approve'])->assertForbidden();
    }
}
