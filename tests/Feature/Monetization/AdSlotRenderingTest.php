<?php

namespace Tests\Feature\Monetization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Public\CreatesPublicFixtures;
use Tests\TestCase;

/**
 * Milestone 18: pins the actual HTTP-response consequences of the
 * <x-ad-slot> component and AdSlotRegistry - not just the registry's
 * own decisions (see AdSlotRegistryTest), but what a visitor's
 * browser actually receives.
 *
 * "data-ad-slot" is what an eligible slot's container is marked up
 * with (see resources/views/components/ad-slot.blade.php) and is
 * used throughout as the signal that active ad markup rendered.
 */
class AdSlotRenderingTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_the_homepage_renders_no_ad_markup_while_monetization_is_disabled_by_default(): void
    {
        $this->publishedArticle();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-ad-slot', false);
        $response->assertDontSee('ad-slot', false);
    }

    public function test_an_article_page_renders_no_ad_markup_while_monetization_is_disabled_by_default(): void
    {
        $article = $this->publishedArticle();

        $response = $this->get("/article/{$article->slug}");

        $response->assertOk();
        $response->assertDontSee('data-ad-slot', false);
        $response->assertDontSee('ad-slot', false);
    }

    public function test_the_homepage_renders_its_eligible_slots_once_monetization_is_enabled(): void
    {
        config(['monetization.enabled' => true]);
        $this->publishedArticle();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-ad-slot="site_top"', false);
        $response->assertSee('data-ad-slot="site_footer"', false);
        $response->assertSee('data-ad-slot="home_between_sections"', false);
        // Only the homepage slots - never an article-only slot here.
        $response->assertDontSee('data-ad-slot="article_top"', false);
    }

    public function test_an_article_page_renders_its_eligible_slots_once_monetization_is_enabled(): void
    {
        config(['monetization.enabled' => true]);
        $article = $this->publishedArticle();

        $response = $this->get("/article/{$article->slug}");

        $response->assertOk();
        $response->assertSee('data-ad-slot="site_top"', false);
        $response->assertSee('data-ad-slot="site_footer"', false);
        $response->assertSee('data-ad-slot="article_top"', false);
        $response->assertSee('data-ad-slot="article_bottom"', false);
        // Only the article-only slot never appears on a page it is not
        // configured for.
        $response->assertDontSee('data-ad-slot="home_between_sections"', false);
    }

    public function test_an_enabled_ad_slot_never_contains_a_provider_script_or_a_publisher_id(): void
    {
        config(['monetization.enabled' => true]);
        $this->publishedArticle();

        $response = $this->get('/');

        $response->assertOk();
        // The page's own SEO JSON-LD <script> is expected and unrelated
        // to monetization; what must never appear is anything ad-specific.
        $response->assertDontSee('pub-', false);
        $response->assertDontSee('googlesyndication', false);
        $response->assertDontSee('adsbygoogle', false);
        $response->assertDontSee('doubleclick', false);
    }

    public function test_a_disabled_slot_reserves_no_layout_space(): void
    {
        // Monetization stays disabled (the default) - the eligible-slot
        // "min-height" reservation must never appear on a page where no
        // slot is eligible.
        $this->publishedArticle();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('min-height', false);
    }

    public function test_the_admin_dashboard_never_renders_ad_markup_even_when_monetization_is_enabled(): void
    {
        config(['monetization.enabled' => true]);
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertDontSee('data-ad-slot', false);
        $response->assertDontSee('ad-slot', false);
    }

    public function test_the_admin_story_queue_never_renders_ad_markup_even_when_monetization_is_enabled(): void
    {
        config(['monetization.enabled' => true]);
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get('/admin/stories');

        $response->assertOk();
        $response->assertDontSee('data-ad-slot', false);
    }

    public function test_the_login_page_never_renders_ad_markup_even_when_monetization_is_enabled(): void
    {
        config(['monetization.enabled' => true]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertDontSee('data-ad-slot', false);
    }
}
