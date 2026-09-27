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
}
