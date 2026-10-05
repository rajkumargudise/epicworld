<?php

namespace Tests\Feature;

use App\Enums\AiResultStatus;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\EditorialJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Editorial\AiBlogGenerator;
use App\Services\Images\ImageFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Public\CreatesPublicFixtures;
use Tests\TestCase;

class BlogsAndImagesTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.default' => 'fake', 'editorial.min_content_length' => 1200]);
    }

    private function longBody(): string
    {
        $section = fn (string $h) => "## {$h}\n\n".str_repeat('This section explains the topic clearly and in depth for readers. ', 8);

        return "An opening paragraph that hooks the reader and explains what this post covers in some detail.\n\n"
            .implode("\n\n", array_map($section, ['Background', 'Key details', 'Why it matters', 'What happens next', 'Bottom line']));
    }

    private function fakeBlog(array $over = []): void
    {
        app(FakeAiProvider::class)->push(AiResult::success('fake', 'fake-model', array_merge([
            'title' => 'How UPI changed everyday payments in India',
            'dek' => 'A look at how instant payments reshaped daily life.',
            'body' => $this->longBody(),
            'key_points' => ['UPI is everywhere.', 'It is fast and cheap.', 'Merchants adopted it quickly.'],
            'tags' => ['UPI', 'Payments', 'Fintech'],
            'image_query' => 'mobile payment',
        ], $over)));
    }

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'finance'], ['name' => 'Finance', 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_the_image_finder_uses_openverse_without_a_key_and_keeps_the_credit(): void
    {
        Http::fake(['api.openverse.org/*' => Http::response(['results' => [
            ['url' => 'http://insecure.example/a.jpg', 'license' => 'by', 'license_version' => '4.0'],
            ['url' => 'https://img.example/nc.jpg', 'license' => 'by-nc'],
            ['url' => 'https://img.example/ok.jpg', 'license' => 'by', 'license_version' => '4.0', 'title' => 'Market stall', 'creator' => 'Jo Photo', 'foreign_landing_url' => 'https://flickr.example/p/1'],
        ]])]);

        $hit = app(ImageFinder::class)->find('mobile payment!!');

        $this->assertSame('https://img.example/ok.jpg', $hit['url']);
        $this->assertSame('openverse', $hit['source']);
        $this->assertStringContainsString('Jo Photo', $hit['credit']);
        $this->assertStringContainsString('CC BY 4.0', $hit['credit']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'license=cc0%2Cpdm%2Cby') || str_contains($r->url(), 'license=cc0,pdm,by'));
    }

    public function test_pexels_is_preferred_when_a_key_is_saved_and_the_key_is_sent_as_a_header(): void
    {
        Setting::write('pexels_api_key', 'pexels-secret');
        Http::fake([
            'api.pexels.com/*' => Http::response(['photos' => [['src' => ['large2x' => 'https://images.pexels.com/p.jpg'], 'photographer' => 'Ana', 'url' => 'https://www.pexels.com/photo/1']]]),
            'api.openverse.org/*' => Http::response(['results' => []]),
        ]);

        $hit = app(ImageFinder::class)->find('classroom');

        $this->assertSame('pexels', $hit['source']);
        $this->assertSame('Photo by Ana on Pexels', $hit['credit']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'pexels.com') && $r->hasHeader('Authorization', 'pexels-secret') && ! str_contains($r->url(), 'pexels-secret'));
    }

    public function test_a_failing_image_source_never_breaks_anything(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertNull(app(ImageFinder::class)->find('anything'));
        $this->assertNull(app(ImageFinder::class)->find('   '));
    }

    public function test_the_blog_generator_writes_a_full_post_with_tags_image_credit_and_sends_it_to_review(): void
    {
        Http::fake(['api.openverse.org/*' => Http::response(['results' => [['url' => 'https://img.example/pay.jpg', 'license' => 'cc0', 'title' => 'Payment', 'creator' => 'Sam']]])]);
        $this->fakeBlog();

        $article = app(AiBlogGenerator::class)->generate('How UPI changed payments', $this->category(), ['UPI launched in 2016'], 'long', [['name' => 'NPCI', 'url' => 'https://www.npci.org.in']]);

        $this->assertSame(ArticleStatus::Review, $article->status);
        $this->assertSame('ai_blog', $article->editorial_metadata['source']);
        $this->assertSame('https://img.example/pay.jpg', $article->featured_image);
        $this->assertStringContainsString('Sam', $article->editorial_metadata['image_credit']['text']);
        $this->assertSame(['UPI', 'Payments', 'Fintech'], $article->tags()->orderBy('name')->pluck('name')->sort()->values()->all() === ['Fintech', 'Payments', 'UPI'] ? ['UPI', 'Payments', 'Fintech'] : []);
        $this->assertCount(3, $article->editorial_metadata['key_points']);
        $this->assertGreaterThan(5, substr_count($article->content, '## ') + 1);

        // Allowed to use background knowledge, with the notes supplied as source material.
        $call = app(FakeAiProvider::class)->calls()[0];
        $this->assertTrue($call->allowBackground);
        $this->assertSame(['UPI launched in 2016'], $call->facts);
    }

    public function test_a_too_short_ai_blog_stays_in_draft_instead_of_the_review_queue(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $this->fakeBlog(['body' => 'One short line.']);

        $article = app(AiBlogGenerator::class)->generate('A topic that gets a one line answer', $this->category());

        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertContains('content_too_short', $article->editorial_metadata['quality']['issues']);
    }

    public function test_an_unavailable_ai_service_is_reported_not_swallowed(): void
    {
        app(FakeAiProvider::class)->push(AiResult::failure(AiResultStatus::RateLimited, 'fake', null, 'no credit'));

        $this->expectException(\App\Services\Ai\Exceptions\AiProviderUnavailableException::class);

        app(AiBlogGenerator::class)->generate('A perfectly fine topic here', $this->category());
    }

    public function test_the_admin_blog_writer_creates_a_review_ready_post_and_never_publishes(): void
    {
        Http::fake(['*' => Http::response(['results' => []], 200)]);
        $this->fakeBlog();
        $editor = User::factory()->editor()->create();
        $category = $this->category();

        $this->actingAs($editor)->get('/admin/blog-writer')->assertOk()->assertSee('Blog writer');

        $response = $this->actingAs($editor)->post('/admin/blog-writer', [
            'topic' => 'How UPI changed everyday payments in India', 'category_id' => $category->id, 'length' => 'long', 'notes' => "UPI launched in 2016\nHandles billions of transactions",
        ]);

        $article = Article::firstOrFail();
        $response->assertRedirect(route('admin.articles.edit', $article));
        $this->assertNotSame(ArticleStatus::Published, $article->status);
        $this->get(route('article.show', $article))->assertNotFound();
    }

    public function test_the_blog_writer_validates_input_and_needs_a_cms_role(): void
    {
        $this->post('/admin/blog-writer', [])->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'contributor']))->post('/admin/blog-writer', [])->assertForbidden();

        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->post('/admin/blog-writer', ['topic' => 'short', 'category_id' => 999, 'length' => 'huge'])->assertSessionHasErrors(['topic', 'category_id', 'length']);
    }

    public function test_an_editor_can_find_a_free_image_for_an_article(): void
    {
        Http::fake(['api.openverse.org/*' => Http::response(['results' => [['url' => 'https://img.example/x.jpg', 'license' => 'cc0', 'title' => 'X', 'creator' => 'Y']]])]);
        $article = $this->publishedArticle(['featured_image' => null]);
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post("/admin/articles/{$article->id}/image", ['query' => 'school'])->assertSessionHas('status');

        $this->assertSame('https://img.example/x.jpg', $article->refresh()->featured_image);
    }

    public function test_the_image_credit_is_shown_under_the_featured_image(): void
    {
        $article = $this->publishedArticle([
            'featured_image' => 'https://img.example/x.jpg',
            'editorial_metadata' => ['image_credit' => ['text' => 'Photo by Ana on Pexels', 'url' => 'https://www.pexels.com/photo/1'], 'source_links' => [['name' => 'NPCI', 'url' => 'https://www.npci.org.in'], ['name' => 'Bad', 'url' => 'javascript:alert(1)']]],
        ]);

        $html = $this->get(route('article.show', $article))->assertOk()->assertSee('Photo by Ana on Pexels')->assertSee('NPCI')->getContent();
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_the_daily_article_cap_pauses_the_queue(): void
    {
        config(['editorial.daily_article_cap' => 1]);
        EditorialJob::query()->delete();
        $story = \App\Models\Story::create(['title' => 'S', 'slug' => 's-1', 'content_hash' => hash('sha256', 's'), 'status' => \App\Enums\StoryStatus::Published, 'facts' => []]);
        EditorialJob::create(['story_id' => $story->id, 'job_type' => 'article_generation', 'status' => 'completed', 'completed_at' => now()]);

        $this->artisan('editorial:process')->expectsOutputToContain('Daily AI article cap reached')->assertSuccessful();
    }

    public function test_the_settings_page_saves_image_keys_and_the_cap(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', ['ai_provider' => 'gemini', 'pexels_api_key' => 'pk-1', 'unsplash_access_key' => 'uk-1', 'ai_daily_article_cap' => 12])->assertRedirect();

        $this->assertSame('pk-1', Setting::read('pexels_api_key'));
        $this->assertSame('uk-1', Setting::read('unsplash_access_key'));
        $this->assertSame('12', Setting::read('ai_daily_article_cap'));
        $this->actingAs($admin)->get('/admin/settings')->assertDontSee('pk-1')->assertSee('Articles');
    }
}
