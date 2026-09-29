<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 17: with APP_DEBUG=false (the required production value -
 * see the .env production contract), Laravel's own exception handler
 * already renders generic error pages instead of a stack trace. These
 * pin that behavior for this application's actual error-producing
 * routes, rather than trusting the framework default was never
 * overridden by a custom error view or handler.
 */
class ErrorResponseSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_a_404_response_does_not_leak_a_stack_trace_or_file_path(): void
    {
        $response = $this->get('/article/does-not-exist');

        $response->assertNotFound();
        $response->assertDontSee('Stack trace', false);
        $response->assertDontSee('/home/', false);
        $response->assertDontSee('vendor/laravel', false);
    }

    public function test_a_403_response_does_not_leak_a_stack_trace_or_file_path(): void
    {
        $user = User::factory()->create(['role' => null]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
        $response->assertDontSee('Stack trace', false);
        $response->assertDontSee('/home/', false);
        $response->assertDontSee('vendor/laravel', false);
    }

    /**
     * CSRF itself is not exercised here: Laravel's own CSRF middleware
     * (PreventRequestForgery, registered by default in the 'web' group)
     * deliberately no-ops under app()->runningUnitTests() - a
     * framework-level test convenience, not an application gap - so a
     * 419 can't be produced through PHPUnit's HTTP test client at all.
     * What's actually verifiable and application-specific is that
     * every state-changing admin form carries @csrf: confirmed by
     * inspection in resources/views/auth/login.blade.php and every
     * admin/articles/* form in resources/views/admin/articles/edit.blade.php.
     */
    public function test_a_malformed_article_slug_is_a_clean_404_not_an_error(): void
    {
        $response = $this->get('/article/'.str_repeat('a%20b/', 20));

        $this->assertContains($response->status(), [404, 400]);
        $response->assertDontSee('Stack trace', false);
    }
}
