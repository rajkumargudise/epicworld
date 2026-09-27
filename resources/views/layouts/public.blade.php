<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-white text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight text-slate-900">
                EPIC <span class="text-indigo-600">World</span>
            </a>

            <nav class="hidden flex-wrap items-center gap-x-5 gap-y-1 text-sm font-medium text-slate-700 lg:flex" aria-label="Primary">
                <a href="{{ route('latest') }}" class="hover:text-indigo-600">Latest</a>
                @foreach ($navCategories ?? [] as $navCategory)
                    @continue($navCategory->slug === 'latest')
                    <a href="{{ route('category.show', $navCategory) }}" class="hover:text-indigo-600">{{ $navCategory->name }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('search') }}" class="hidden lg:block" role="search">
                <label for="nav-search-q" class="sr-only">Search articles</label>
                <input id="nav-search-q" type="search" name="q" value="{{ request()->query('q') }}"
                       placeholder="Search&hellip;"
                       class="w-48 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:w-64 focus:outline-none">
            </form>
        </div>

        <div class="border-t border-slate-100 lg:hidden">
            <nav class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2 text-xs font-medium text-slate-600" aria-label="Primary, compact">
                <a href="{{ route('latest') }}" class="hover:text-indigo-600">Latest</a>
                @foreach ($navCategories ?? [] as $navCategory)
                    @continue($navCategory->slug === 'latest')
                    <a href="{{ route('category.show', $navCategory) }}" class="hover:text-indigo-600">{{ $navCategory->name }}</a>
                @endforeach
                <a href="{{ route('search') }}" class="font-semibold text-indigo-600">Search</a>
            </nav>
        </div>
    </header>

    @include('partials.ad-slot', ['slot' => 'top-banner'])

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
        @yield('content')
    </main>

    @include('partials.ad-slot', ['slot' => 'footer-banner'])

    <footer class="border-t border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-500 sm:px-6">
            @if (($popularTags ?? collect())->isNotEmpty())
                <div class="mb-6">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Popular tags</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($popularTags as $popularTag)
                            <a href="{{ route('tag.show', $popularTag) }}"
                               class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200 hover:text-indigo-600">
                                #{{ $popularTag->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            <p>&copy; {{ now()->year }} EPIC World. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
