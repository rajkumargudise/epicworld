<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    @include('partials.seo')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-extrabold tracking-tight">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm font-black text-white">E</span>
                <span>EPIC <span class="text-brand-600">World</span></span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium text-slate-600 lg:flex" aria-label="Primary">
                <a href="{{ route('latest') }}" class="rounded-md px-3 py-1.5 transition hover:bg-slate-100 hover:text-slate-900 {{ request()->routeIs('latest') ? 'bg-slate-100 text-slate-900' : '' }}">Latest</a>
                @foreach (($navCategories ?? collect())->reject(fn ($c) => $c->slug === 'latest')->take(7) as $navCategory)
                    <a href="{{ route('category.show', $navCategory) }}"
                       class="rounded-md px-3 py-1.5 transition hover:bg-slate-100 hover:text-slate-900 {{ request()->is('category/'.$navCategory->slug) ? 'bg-slate-100 text-slate-900' : '' }}">{{ $navCategory->name }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('search') }}" class="hidden sm:block" role="search">
                    <label for="nav-search-q" class="sr-only">Search articles</label>
                    <input id="nav-search-q" type="search" name="q" value="{{ request()->query('q') }}" placeholder="Search&hellip;"
                           class="w-36 rounded-full border border-slate-200 bg-slate-100 px-4 py-1.5 text-sm outline-none transition-all focus:w-56 focus:border-brand-500 focus:bg-white">
                </form>
                <button type="button" id="menu-toggle" class="grid h-9 w-9 place-items-center rounded-md text-slate-700 hover:bg-slate-100 lg:hidden"
                        aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden border-t border-slate-200 bg-white lg:hidden">
            <form method="GET" action="{{ route('search') }}" class="px-4 pt-3" role="search">
                <label for="mobile-search-q" class="sr-only">Search articles</label>
                <input id="mobile-search-q" type="search" name="q" placeholder="Search articles&hellip;"
                       class="w-full rounded-full border border-slate-200 bg-slate-100 px-4 py-2 text-sm outline-none focus:border-brand-500 focus:bg-white">
            </form>
            <nav class="grid grid-cols-2 gap-1 p-3 text-sm font-medium text-slate-700" aria-label="Primary, mobile">
                <a href="{{ route('latest') }}" class="rounded-md px-3 py-2 hover:bg-slate-100">Latest</a>
                @foreach (($navCategories ?? collect())->reject(fn ($c) => $c->slug === 'latest') as $navCategory)
                    <a href="{{ route('category.show', $navCategory) }}" class="rounded-md px-3 py-2 hover:bg-slate-100">{{ $navCategory->name }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <x-ad-slot name="site_top" :context="$context ?? 'unknown'" />

    <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10">
        @yield('content')
    </main>

    <x-ad-slot name="site_footer" :context="$context ?? 'unknown'" />

    <footer class="mt-8 border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <div class="grid gap-8 md:grid-cols-3">
                <div>
                    <a href="{{ route('home') }}" class="text-lg font-extrabold tracking-tight">EPIC <span class="text-brand-600">World</span></a>
                    <p class="mt-2 max-w-xs text-sm text-slate-500">Clear, well-sourced coverage of technology, AI, business, science and more.</p>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Explore</h2>
                    <ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-slate-600">
                        <li><a href="{{ route('latest') }}" class="hover:text-brand-600">Latest</a></li>
                        @foreach (($navCategories ?? collect())->reject(fn ($c) => $c->slug === 'latest')->take(7) as $navCategory)
                            <li><a href="{{ route('category.show', $navCategory) }}" class="hover:text-brand-600">{{ $navCategory->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                @if (($popularTags ?? collect())->isNotEmpty())
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Popular tags</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($popularTags as $popularTag)
                                <a href="{{ route('tag.show', $popularTag) }}"
                                   class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-brand-50 hover:text-brand-700">#{{ $popularTag->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <p class="mt-8 border-t border-slate-100 pt-6 text-xs text-slate-500">
                &copy; {{ now()->year }} EPIC World. All rights reserved.
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('privacy') }}" class="hover:text-brand-600">Privacy</a>
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('sitemap') }}" class="hover:text-brand-600">Sitemap</a>
            </p>
        </div>
    </footer>
</body>
</html>
