@php
    $user = auth()->user();
    $nav = $user ? [
        'Overview' => [
            ['admin.dashboard', 'Dashboard', 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ],
        'Content' => [
            ['admin.blog-writer.create', 'Write blog', 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z'],
            ['admin.review.index', 'Review queue', 'M9 11l3 3 8-8M20 12v7a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h9'],
            ['admin.comments.index', 'Comments', 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z'],
            ['admin.messages.index', 'Messages', 'M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zM22 6l-10 7L2 6'],
        ],
        'Newsroom' => [
            ['admin.stories.index', 'Stories', 'M4 19.5A2.5 2.5 0 016.5 17H20M4 4.5A2.5 2.5 0 016.5 2H20v20H6.5A2.5 2.5 0 014 19.5z'],
            ['admin.feeds.index', 'News feeds', 'M4 11a9 9 0 019 9M4 4a16 16 0 0116 16M5 19h.01'],
        ],
        'Growth' => [
            ['admin.launch', 'Google & SEO', 'M11 3a8 8 0 105.3 14l4.4 4.3 1.3-1.3-4.3-4.4A8 8 0 0011 3z'],
        ],
    ] : [];
    if ($user && $user->isAdmin()) {
        $nav['Admin'] = [
            ['admin.users.index', 'Users', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8'],
            ['admin.settings.edit', 'Settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z'],
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#07070d">
    <title>@yield('title', 'EPIC World Admin')</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-32x32.png">
    <script>
        try { var t = localStorage.getItem('theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell min-h-screen">
    @auth
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside id="admin-sidebar" class="admin-sidebar fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-line bg-surface transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-line px-5">
                <a href="{{ route('admin.dashboard') }}" aria-label="EPIC World admin">@include('partials.logo', ['height' => 30])</a>
                <button type="button" data-admin-nav-close class="grid h-9 w-9 place-items-center rounded-lg text-muted hover:text-ink lg:hidden" aria-label="Close menu">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Admin">
                @foreach ($nav as $group => $items)
                    <div>
                        <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">{{ $group }}</p>
                        <ul class="space-y-0.5">
                            @foreach ($items as [$route, $label, $icon])
                                @php $active = request()->routeIs(substr_count($route, '.') >= 2 ? \Illuminate\Support\Str::beforeLast($route, '.').'.*' : $route); @endphp
                                <li>
                                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                                       class="admin-nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $active ? 'is-active' : '' }}">
                                        <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-line p-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-semibold text-white" style="background:linear-gradient(135deg,var(--accent),var(--accent-2))">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $user->name }}</p>
                        <p class="text-xs capitalize text-muted">{{ $user->role ?? 'no role' }}</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs">
                    <a href="{{ route('admin.password.edit') }}" class="rounded-md border border-line px-2.5 py-1.5 text-ink-soft hover:text-ink">Password</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-line px-2.5 py-1.5 text-ink-soft hover:text-ink">Log out</button>
                    </form>
                </div>
            </div>
        </aside>
        <div id="admin-backdrop" class="fixed inset-0 z-30 hidden bg-black/60 lg:hidden"></div>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-line bg-bg/85 px-4 backdrop-blur sm:px-8">
                <div class="flex items-center gap-3">
                    <button type="button" data-admin-nav-open class="grid h-10 w-10 place-items-center rounded-lg border border-line text-ink-soft lg:hidden" aria-label="Open menu">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <div class="text-base font-semibold text-ink sm:text-lg">@yield('heading', trim(str_replace('— EPIC World Admin', '', $__env->yieldContent('title', 'Admin'))))</div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.blog-writer.create') }}" class="btn-primary hidden rounded-lg px-4 py-2 text-sm font-semibold sm:inline-block">+ New blog</a>
                    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="rounded-lg border border-line px-3 py-2 text-sm text-ink-soft hover:text-ink">View site &nearr;</a>
                    <button type="button" data-theme-toggle class="grid h-10 w-10 place-items-center rounded-lg border border-line text-ink-soft hover:text-ink" aria-label="Toggle light or dark theme">
                        <svg class="hidden h-[18px] w-[18px] [[data-theme=dark]_&]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                        <svg class="block h-[18px] w-[18px] [[data-theme=dark]_&]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    </button>
                </div>
            </header>

            <main class="admin-content mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-8">
                @include('layouts.partials.admin-flash')
                @yield('content')
            </main>
        </div>
    </div>
    @else
    <main class="admin-content mx-auto max-w-6xl px-4 py-8 sm:px-8">
        @include('layouts.partials.admin-flash')
        @yield('content')
    </main>
    @endauth

    <script>
        (function () {
            var sb = document.getElementById('admin-sidebar'), bd = document.getElementById('admin-backdrop');
            if (!sb) return;
            function set(open) { sb.classList.toggle('-translate-x-full', !open); bd.classList.toggle('hidden', !open); }
            document.querySelectorAll('[data-admin-nav-open]').forEach(function (b) { b.addEventListener('click', function () { set(true); }); });
            document.querySelectorAll('[data-admin-nav-close]').forEach(function (b) { b.addEventListener('click', function () { set(false); }); });
            bd.addEventListener('click', function () { set(false); });
        })();
    </script>
</body>
</html>
