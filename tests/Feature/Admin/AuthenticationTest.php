<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_form_is_reachable(): void
    {
        $this->get('/login')->assertOk()->assertSee('EPIC World Admin');
    }

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->editor()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_cannot_log_in_with_incorrect_credentials(): void
    {
        $user = User::factory()->editor()->create(['password' => bcrypt('correct-password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_guest_is_redirected_to_login_when_visiting_the_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_a_guest_is_redirected_to_login_when_visiting_the_story_queue(): void
    {
        $this->get('/admin/stories')->assertRedirect('/login');
    }

    /**
     * Milestone 17: Auth::attempt() previously had no rate limiting at
     * all behind it. The 6th attempt within a minute (5 allowed) for
     * the same email+IP must be throttled, not silently accepted into
     * another credential check.
     */
    public function test_repeated_failed_login_attempts_are_throttled(): void
    {
        $user = User::factory()->editor()->create(['password' => bcrypt('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $response->assertStatus(429);
    }

    public function test_the_login_throttle_does_not_block_a_correct_attempt_within_the_limit(): void
    {
        $user = User::factory()->editor()->create(['password' => bcrypt('correct-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
