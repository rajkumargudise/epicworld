<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Exceptions\UnknownAiProviderException;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Ai\Providers\GeminiProvider;
use Tests\TestCase;

class AiProviderManagerTest extends TestCase
{
    public function test_it_resolves_the_configured_default_provider(): void
    {
        config(['ai.default' => 'fake']);

        $provider = app(AiProviderManager::class)->resolve();

        $this->assertInstanceOf(FakeAiProvider::class, $provider);
        $this->assertSame('fake', $provider->name());
    }

    public function test_it_resolves_an_explicitly_named_driver_overriding_the_default(): void
    {
        config(['ai.default' => 'fake']);

        $provider = app(AiProviderManager::class)->resolve('gemini');

        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertSame('gemini', $provider->name());
    }

    public function test_an_unknown_driver_fails_clearly_instead_of_falling_back(): void
    {
        config(['ai.default' => 'fake']);

        $this->expectException(UnknownAiProviderException::class);

        app(AiProviderManager::class)->resolve('does-not-exist');
    }

    public function test_a_configured_provider_with_no_matching_entry_fails_clearly(): void
    {
        config(['ai.default' => 'nonexistent-driver', 'ai.providers' => ['fake' => ['driver' => 'fake']]]);

        $this->expectException(UnknownAiProviderException::class);

        app(AiProviderManager::class)->resolve();
    }

    public function test_the_same_fake_provider_instance_is_returned_across_resolutions(): void
    {
        config(['ai.default' => 'fake']);
        $manager = app(AiProviderManager::class);

        $first = $manager->resolve('fake');
        $second = $manager->resolve('fake');

        $this->assertSame($first, $second);
    }
}
