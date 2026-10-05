<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Gate;
use App\Services\Ai\Providers\FakeAiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->app->singleton(SiteSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Milestone 17: several migrations put unique() on a plain
        // string() column (slug, content_hash, canonical_url_hash,
        // uuid, ...). Laravel's default string() is varchar(255),
        // which under utf8mb4 (4 bytes/char) needs a 1020-byte index
        // key - over the 767-byte limit MySQL/MariaDB enforces unless
        // innodb_large_prefix + Barracuda/DYNAMIC row format are all
        // explicitly on, which is not guaranteed on shared hosting.
        // This is Laravel's own long-standing documented fix: it only
        // changes the default length new migrations use (191*4=764
        // bytes, safely under the limit), not app behavior, and it is
        // a no-op for SQLite (tests), which doesn't enforce a varchar
        // length limit either way.
        Schema::defaultStringLength(191);

        // The public layout's taxonomy nav and footer tag list are
        // shared here rather than fetched in every public controller -
        // one query per request each, computed once, the same for
        // every public page.
        View::composer('layouts.public', function ($view): void {
            $view->with('navCategories', Category::active()->get());
            $view->with('popularTags', $this->popularTags());
        });

        // Shared with every view (child views render before the layout,
        // so a layout-only composer wouldn't reach them).
        View::share('site', app(SiteSettings::class));

        // Milestone 17: the login form had no rate limiting at all -
        // Auth::attempt() was reachable an unlimited number of times.
        // Keyed by email+IP (Laravel's own established convention, the
        // same one Breeze/Fortify use), not IP alone, so this limits
        // both a single IP hammering many accounts and a distributed
        // attempt against one account. Uses the framework's built-in
        // cache-backed RateLimiter/ThrottleRequests - no new
        // dependency, and the database cache store already configured
        // for this project makes it Hostinger-safe without Redis.
        // Only editors and admins may enter the CMS; contributors and
        // readers who register publicly never can.
        Gate::define('access-admin', fn (User $user) => $user->isEditor() && ! $user->isSuspended());

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(10)->by($request->ip()),
        ]);

        RateLimiter::for('comment', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(2)->by($request->ip()),
            Limit::perDay(8)->by($request->ip()),
        ]);

        RateLimiter::for('submit-post', fn (Request $request) => Limit::perHour(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
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
