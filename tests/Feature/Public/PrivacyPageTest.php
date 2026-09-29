<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 18: pins that the privacy page exists, is reachable, and
 * never claims a compliance status or an active third-party provider
 * this application does not actually have.
 */
class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_page_is_reachable(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Privacy');
    }

    public function test_the_privacy_page_states_monetization_is_disabled_by_default(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('disabled by default', false);
    }

    public function test_the_privacy_page_does_not_claim_legal_compliance(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertDontSee('GDPR compliant', false);
        $response->assertDontSee('DPDP compliant', false);
        $response->assertDontSee('CCPA compliant', false);
        $response->assertDontSee('fully compliant', false);
    }

    public function test_the_privacy_page_does_not_claim_an_active_advertising_provider(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertDontSee('googlesyndication', false);
        $response->assertDontSee('adsbygoogle', false);
    }

    public function test_the_privacy_page_is_linked_from_the_public_footer(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('privacy'), false);
    }
}
