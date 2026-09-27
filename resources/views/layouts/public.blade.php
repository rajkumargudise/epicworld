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
        </div>

        <div class="border-t border-slate-100 lg:hidden">
            <nav class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2 text-xs font-medium text-slate-600" aria-label="Primary, compact">
                <a href="{{ route('latest') }}" class="hover:text-indigo-600">Latest</a>
                @foreach ($navCategories ?? [] as $navCategory)
                    @continue($navCategory->slug === 'latest')
                    <a href="{{ route('category.show', $navCategory) }}" class="hover:text-indigo-600">{{ $navCategory->name }}</a>
                @endforeach
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
            <p>&copy; {{ now()->year }} EPIC World. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
