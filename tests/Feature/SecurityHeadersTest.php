<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Milestone 17: a conservative set of headers that carry no risk of
 * breaking a page (unlike CSP or HSTS - see SecurityHeaders'
 * docblock) applied to every response, public and admin alike.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_the_homepage_carries_the_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
    }

    public function test_the_login_page_also_carries_the_baseline_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_no_content_security_policy_or_hsts_header_is_sent_yet(): void
    {
        // Deliberately not sent until Hostinger HTTPS is confirmed and
        // asset origins are audited for a real CSP - see
        // SecurityHeaders' docblock. This pins that omission is
        // intentional, not an oversight a future change should "fix"
        // without that verification.
        $response = $this->get('/');

        $response->assertHeaderMissing('Strict-Transport-Security');
        $response->assertHeaderMissing('Content-Security-Policy');
    }
}
