<?php

namespace App\Providers;

use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so AiProviderManager::resolve('fake') and a
        // test's own app(FakeAiProvider::class) calls (to push
        // canned results or inspect calls()) share the same instance.
        $this->app->singleton(FakeAiProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
