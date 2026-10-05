<?php

namespace Tests\Feature\Community;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Feature\Public\CreatesPublicFixtures;
use Tests\TestCase;

class GoogleAndPagesTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    private function settings(array $over = []): array
    {
        return array_merge(['ai_provider' => 'gemini'], $over);
    }

    public function test_ads_txt_is_404_until_a_publisher_id_is_saved_then_is_exact(): void
    {
        $this->get('/ads.txt')->assertNotFound();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/settings', $this->settings(['adsense_publisher_id' => '1234567890123456']))->assertRedirect();

        $this->get('/ads.txt')->assertOk()->assertSee('google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0', false);
    }

    public function test_the_settings_page_validates_and_normalises_google_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', $this->settings(['ga4_measurement_id' => 'UA-123']))->assertSessionHasErrors('ga4_measurement_id');
        $this->actingAs($admin)->put('/admin/settings', $this->settings(['adsense_publisher_id' => 'not-an-id']))->assertSessionHasErrors('adsense_publisher_id');
        $this->actingAs($admin)->put('/admin/settings', $this->settings(['gsc_verification' => 'short']))->assertSessionHasErrors('gsc_verification');
        $this->actingAs($admin)->put('/admin/settings', $this->settings(['contact_email' => 'nope']))->assertSessionHasErrors('contact_email');

        $token = 'abcdefghijklmnopqrstuvwxyz0123456789ABCD';
        $this->actingAs($admin)->put('/admin/settings', $this->settings([
            'ga4_measurement_id' => 'G-ABC123DEF4',
            'gsc_verification' => '<meta name="google-site-verification" content="'.$token.'" />',
            'adsense_publisher_id' => 'ca-pub-1234567890123456',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('G-ABC123DEF4', Setting::read('ga4_measurement_id'));
        $this->assertSame($token, Setting::read('gsc_verification'));
        $this->assertSame('ca-pub-1234567890123456', Setting::read('adsense_publisher_id'));
    }

    public function test_only_admins_can_change_google_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->put('/admin/settings', $this->settings(['ga4_measurement_id' => 'G-ABC123DEF4']))->assertForbidden();
        $this->assertNull(Setting::read('ga4_measurement_id'));
    }

    public function test_verification_tags_appear_but_no_google_script_is_ever_rendered_server_side(): void
    {
        Setting::write('gsc_verification', 'abcdefghijklmnopqrstuvwxyz0123456789ABCD');
        Setting::write('ga4_measurement_id', 'G-ABC123DEF4');
        Setting::write('adsense_publisher_id', 'ca-pub-1234567890123456');
        Setting::write('adsense_enabled', '1');
        $this->publishedArticle();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('name="google-site-verification" content="abcdefghijklmnopqrstuvwxyz0123456789ABCD"', $html);
        $this->assertStringContainsString('name="google-adsense-account" content="ca-pub-1234567890123456"', $html);
        // GA/AdSense are loaded by JS only after consent - never in the HTML itself.
        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('googlesyndication.com', $html);
        $this->assertStringNotContainsString('adsbygoogle', $html);
        // The consent machinery is present.
        $this->assertStringContainsString('id="consent-banner"', $html);
        $this->assertStringContainsString('name="epic-ga4" content="G-ABC123DEF4"', $html);
        $this->assertStringContainsString('name="epic-adsense"', $html);
    }

    public function test_ads_are_never_requested_on_live_desk_account_or_login_pages(): void
    {
        Setting::write('adsense_publisher_id', 'ca-pub-1234567890123456');
        Setting::write('adsense_enabled', '1');

        foreach (['/live', '/live/world', '/login', '/register', '/privacy', '/contact'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('name="epic-adsense"', false);
        }

        $this->actingAs(User::factory()->create(['role' => 'contributor']))->get('/account')->assertOk()->assertDontSee('name="epic-adsense"', false);
    }

    public function test_ads_need_both_an_id_and_the_enable_switch(): void
    {
        Setting::write('adsense_publisher_id', 'ca-pub-1234567890123456');
        $this->publishedArticle();

        $this->get('/')->assertDontSee('name="epic-adsense"', false);

        Setting::write('adsense_enabled', '1');
        $this->get('/')->assertSee('name="epic-adsense"', false);
    }

    public function test_the_privacy_policy_tells_the_truth_about_what_is_enabled(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('disabled by default', false)->assertDontSee('Google AdSense')->assertDontSee('Google Analytics');

        Setting::write('adsense_publisher_id', 'ca-pub-1234567890123456');
        Setting::write('adsense_enabled', '1');
        Setting::write('ga4_measurement_id', 'G-ABC123DEF4');

        $this->get('/privacy')->assertOk()->assertSee('Google AdSense')->assertSee('Google Analytics')->assertDontSee('disabled by default', false)->assertDontSee('adsbygoogle', false);
    }

    public function test_no_consent_banner_or_cookie_link_when_nothing_needs_consent(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="consent-banner"', false)->assertDontSee('Cookie settings');
    }

    public function test_the_news_sitemap_lists_only_fresh_published_articles(): void
    {
        $fresh = $this->publishedArticle(['title' => 'Fresh story', 'published_at' => now()->subHours(3)]);
        $old = $this->publishedArticle(['title' => 'Old story', 'published_at' => now()->subDays(5)]);
        $draft = $this->publishedArticle(['title' => 'Draft story', 'status' => \App\Enums\ArticleStatus::Draft, 'published_at' => null]);

        $response = $this->get('/sitemap-news.xml')->assertOk();
        $this->assertStringContainsString('xml', $response->headers->get('Content-Type'));
        $response->assertSee('<news:title>Fresh story</news:title>', false)
            ->assertSee(route('article.show', $fresh), false)
            ->assertDontSee('Old story')
            ->assertDontSee('Draft story');
    }

    public function test_the_sitemap_and_robots_include_the_trust_pages_and_news_sitemap(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach (['about', 'contact', 'editorial.policy', 'terms', 'write', 'privacy'] as $page) {
            $this->assertStringContainsString(route($page), $sitemap);
        }

        $this->get('/robots.txt')->assertSee('Sitemap: '.route('sitemap.news'), false)->assertSee('Disallow: /account', false);
    }

    public function test_a_staging_address_blocks_all_search_engines(): void
    {
        config(['app.url' => 'https://staging.example.com']);

        $this->get('/robots.txt')->assertSee('Disallow: /', false)->assertDontSee('Allow: /', false)->assertDontSee('Sitemap:', false);
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        config(['app.url' => 'https://epicworld.in']);
        $this->get('/robots.txt')->assertSee('Allow: /', false);
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_the_required_pages_exist_and_are_linked_from_the_footer(): void
    {
        foreach (['/about', '/contact', '/editorial-policy', '/terms', '/write-for-us', '/privacy'] as $url) {
            $this->get($url)->assertOk();
        }

        $home = $this->get('/')->getContent();
        foreach (['about', 'contact', 'editorial.policy', 'terms', 'write', 'privacy'] as $page) {
            $this->assertStringContainsString(route($page), $home);
        }
    }

    public function test_the_contact_form_stores_messages_and_blocks_bots(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $token = fn (int $ago = 10) => Crypt::encryptString((string) (now()->timestamp - $ago));
        $good = ['ct' => $token(), 'name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'A correction', 'message' => 'There is a typo in your article.'];

        $this->post('/contact', $good)->assertRedirect(route('contact'))->assertSessionHas('status');
        $this->assertSame(1, ContactMessage::count());

        $this->post('/contact', $good + ['website' => 'http://spam'])->assertSessionHas('status');
        $this->post('/contact', array_merge($good, ['ct' => $token(0)]))->assertSessionHas('error');
        $this->post('/contact', array_merge($good, ['email' => 'bad']))->assertSessionHasErrors('email');
        $this->post('/contact', array_merge($good, ['subject' => '<b>x</b>']))->assertSessionHasErrors('subject');
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_editors_can_read_and_manage_messages(): void
    {
        $message = ContactMessage::create(['name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'Hello there', 'message' => '<script>x</script> Please reply']);
        $editor = User::factory()->editor()->create();

        $html = $this->actingAs($editor)->get('/admin/messages')->assertOk()->assertSee('Hello there')->getContent();
        $this->assertStringNotContainsString('<script>x</script>', $html);

        $this->actingAs($editor)->patch("/admin/messages/{$message->id}/read");
        $this->assertNotNull($message->refresh()->read_at);

        $this->actingAs($editor)->delete("/admin/messages/{$message->id}");
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_google_readiness_page_is_honest_and_the_dashboard_shows_the_new_cards(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin/launch')->assertOk()
            ->assertSee('AdSense approval is decided by Google')
            ->assertSee('Search Console')
            ->assertSee('ads.txt');

        $this->actingAs($editor)->get('/admin')->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('Comments awaiting approval')
            ->assertSee('Google readiness checks')
            ->assertSee('Site health');
    }

    public function test_the_admin_login_page_still_says_admin(): void
    {
        $this->get('/login')->assertOk()->assertSee('EPIC World Admin')->assertSee('Create a contributor account');
    }
}
