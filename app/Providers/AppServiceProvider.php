<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Tag;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Support\Collection;
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
        // The public layout's taxonomy nav and footer tag list are
        // shared here rather than fetched in every public controller -
        // one query per request each, computed once, the same for
        // every public page.
        View::composer('layouts.public', function ($view): void {
            $view->with('navCategories', Category::active()->get());
            $view->with('popularTags', $this->popularTags());
        });
    }

    /**
     * The footer's tag discovery list. "Popular" here is a real,
     * deterministic count of each tag's publicly visible articles -
     * never an invented popularity or engagement score - ordered by
     * that count desc, then name asc so ties don't reorder between
     * requests. Bounded to 15 regardless of how many tags exist.
     *
     * @return Collection<int, Tag>
     */
    private function popularTags(): Collection
    {
        return Tag::query()
            ->withCount(['articles' => fn ($query) => $query->publiclyVisible()])
            ->get()
            ->filter(fn (Tag $tag) => $tag->articles_count > 0)
            ->sortBy([
                ['articles_count', 'desc'],
                ['name', 'asc'],
            ])
            ->take(15)
            ->values();
    }
}
