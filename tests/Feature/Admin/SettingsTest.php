<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_save_the_provider_and_key_and_the_key_is_stored_encrypted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings', [
            'ai_provider' => 'openai',
            'openai_model' => 'gpt-4o-mini',
            'openai_api_key' => 'sk-test-secret-123',
        ])->assertRedirect('/admin/settings');

        $this->assertSame('sk-test-secret-123', Setting::read('openai_api_key'));
        $this->assertStringNotContainsString('sk-test-secret-123', (string) Setting::query()->where('key', 'openai_api_key')->value('value'));

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertDontSee('sk-test-secret-123')->assertSee('An API key is set.');
    }

    public function test_a_blank_key_field_keeps_the_stored_key(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::write('openai_api_key', 'sk-keep-me');

        $this->actingAs($admin)->put('/admin/settings', ['ai_provider' => 'openai', 'openai_api_key' => ''])->assertRedirect();

        $this->assertSame('sk-keep-me', Setting::read('openai_api_key'));
    }

    public function test_an_editor_cannot_view_or_change_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();
        $this->actingAs($editor)->put('/admin/settings', ['ai_provider' => 'openai'])->assertForbidden();
    }

    public function test_the_manager_uses_the_admin_selected_provider_and_key_outside_tests(): void
    {
        config(['ai.default' => 'gemini']);
        Setting::write('ai_provider', 'openai');
        Setting::write('openai_api_key', 'sk-from-admin');

        $this->assertInstanceOf(OpenAiProvider::class, app(AiProviderManager::class)->resolve());
    }

    public function test_the_fake_provider_is_never_overridden_by_stored_settings(): void
    {
        config(['ai.default' => 'fake']);
        Setting::write('ai_provider', 'openai');

        $this->assertSame('fake', app(AiProviderManager::class)->resolve()->name());
    }

    public function test_the_provider_is_not_ready_until_a_key_exists_and_the_queue_is_left_alone(): void
    {
        config(['ai.default' => 'openai', 'ai.providers.openai.api_key' => null]);
        $manager = app(AiProviderManager::class);

        $this->assertFalse($manager->isReady());
        $this->artisan('editorial:process')->expectsOutputToContain('no API key yet')->assertSuccessful();

        Setting::write('openai_api_key', 'sk-now-set');
        $this->assertTrue($manager->isReady());

        config(['ai.default' => 'fake']);
        $this->assertTrue($manager->isReady());
    }

    public function test_a_user_can_change_their_own_password(): void
    {
        $user = User::factory()->admin()->create(['password' => 'old-password-12345']);

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'old-password-12345',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ])->assertRedirect('/admin/account/password');

        $this->assertTrue(\Hash::check('brand-new-password-1', $user->fresh()->password));
    }

    public function test_admin_create_command_makes_an_admin_with_a_hashed_password(): void
    {
        $this->artisan('admin:create', ['email' => 'Boss@Example.com'])->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertNotEmpty($user->password);
    }
}
