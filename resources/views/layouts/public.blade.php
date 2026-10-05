<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07070d">
    <script>
        document.documentElement.classList.add('js');
        try { var t = localStorage.getItem('theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @include('partials.seo')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-bg text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2 focus:shadow">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-line bg-bg/75 backdrop-blur-xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5 whitespace-nowrap text-lg font-extrabold tracking-tight">
                <span class="grid h-8 w-8 place-items-center rounded-lg text-sm font-black text-white" style="background: linear-gradient(135deg, var(--accent), var(--accent-2))">E</span>
                <span>EPIC <span class="gradient-text">World</span></span>
            </a>

            <nav class="hidden items-center gap-0.5 whitespace-nowrap text-sm font-medium text-muted xl:flex" aria-label="Primary">
                <a href="{{ route('latest') }}" class="rounded-full px-3.5 py-1.5 transition hover:text-ink {{ request()->routeIs('latest') ? 'bg-surface-2 text-ink' : '' }}">Latest</a>
                @foreach (($navCategories ?? collect())->reject(fn ($c) => $c->slug === 'latest')->take(5) as $navCategory)
                    <a href="{{ route('category.show', $navCategory) }}"
                       class="rounded-full px-3.5 py-1.5 transition hover:text-ink {{ request()->is('category/'.$navCategory->slug) ? 'bg-surface-2 text-ink' : '' }}">{{ $navCategory->name }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('search') }}" class="hidden sm:block" role="search">
                    <label for="nav-search-q" class="sr-only">Search articles</label>
                    <input id="nav-search-q" type="search" name="q" value="{{ request()->query('q') }}" placeholder="Search&hellip;"
                           class="w-36 rounded-full border border-line bg-surface px-4 py-1.5 text-sm text-ink outline-none transition-all placeholder:text-muted focus:w-56 focus:border-accent">
                </form>
                <button type="button" data-theme-toggle class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-ink-soft transition hover:text-ink" aria-label="Toggle light or dark theme">
                    <svg class="hidden h-[18px] w-[18px] [[data-theme=dark]_&]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg class="block h-[18px] w-[18px] [[data-theme=dark]_&]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                </button>
                <button type="button" id="menu-toggle" class="grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-ink-soft hover:text-ink xl:hidden"
                        aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden border-t border-line bg-bg-soft xl:hidden">
            <form method="GET" action="{{ route('search') }}" class="px-4 pt-3" role="search">
                <label for="mobile-search-q" class="sr-only">Search articles</label>
                <input id="mobile-search-q" type="search" name="q" placeholder="Search articles&hellip;"
                       class="w-full rounded-full border border-line bg-surface px-4 py-2 text-sm text-ink outline-none placeholder:text-muted focus:border-accent">
            </form>
            <nav class="grid grid-cols-2 gap-1 p-3 text-sm font-medium text-ink-soft" aria-label="Primary, mobile">
                <a href="{{ route('latest') }}" class="rounded-lg px-3 py-2 hover:bg-surface-2">Latest</a>
                @foreach (($navCategories ?? collect())->reject(fn ($c) => $c->slug === 'latest') as $navCategory)
                    <a href="{{ route('category.show', $navCategory) }}" class="rounded-lg px-3 py-2 hover:bg-surface-2">{{ $navCategory->name }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <x-ad-slot name="site_top" :context="$context ?? 'unknown'" />

    <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10">
        @yield('content')
    </main>

    <x-ad-slot name="site_footer" :context="$context ?? 'unknown'" />

    <footer class="mt-10 border-t border-line bg-bg-soft">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="grid gap-10 md:grid-cols-3">
                <div>
                    <a href="{{ route('home') }}" class="text-lg font-extrabold tracking-tight">EPIC <span class="gradient-text">World</span></a>
                    <p class="mt-3 max-w-xs text-sm leading-relaxed text-muted">Clear, well-sourced coverage of technology, AI, business, science and more.</p>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-muted">Explore</h2>
                    <ul class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm text-ink-soft">
                        <li><a href="{{ route('latest') }}" class="transition hover:text-accent">Latest</a></li>
                        @foreach (($navCategories ?? collect())->reject(fn ($c) => $c->slug === 'latest')->take(7) as $navCategory)
                            <li><a href="{{ route('category.show', $navCategory) }}" class="transition hover:text-accent">{{ $navCategory->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                @if (($popularTags ?? collect())->isNotEmpty())
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-muted">Popular tags</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($popularTags as $popularTag)
                                <a href="{{ route('tag.show', $popularTag) }}" class="chip rounded-full px-3 py-1 text-xs font-medium">#{{ $popularTag->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <p class="mt-10 border-t border-line pt-6 text-xs text-muted">
                &copy; {{ now()->year }} EPIC World. All rights reserved.
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('privacy') }}" class="hover:text-accent">Privacy</a>
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('sitemap') }}" class="hover:text-accent">Sitemap</a>
            </p>
        </div>
    </footer>
</body>
</html>
