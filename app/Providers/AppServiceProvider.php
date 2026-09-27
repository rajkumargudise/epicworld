<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Support\Facades\View;
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
        // The public layout's taxonomy nav is shared here rather than
        // fetched in every public controller - one query per request,
        // computed once, the same active-category list every public
        // page's <nav> needs.
        View::composer('layouts.public', function ($view): void {
            $view->with('navCategories', Category::active()->get());
        });
    }
}
