<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EPIC World Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    @auth
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <nav class="flex items-center gap-6 text-sm font-medium">
                <a href="{{ route('admin.dashboard') }}" class="font-semibold">EPIC World</a>
                <a href="{{ route('admin.dashboard') }}" class="text-slate-600 hover:text-slate-900">Dashboard</a>
                <a href="{{ route('admin.review.index') }}" class="text-slate-600 hover:text-slate-900">Review</a>
                <a href="{{ route('admin.comments.index') }}" class="text-slate-600 hover:text-slate-900">Comments</a>
                <a href="{{ route('admin.messages.index') }}" class="text-slate-600 hover:text-slate-900">Messages</a>
                <a href="{{ route('admin.stories.index') }}" class="text-slate-600 hover:text-slate-900">Stories</a>
                <a href="{{ route('admin.feeds.index') }}" class="text-slate-600 hover:text-slate-900">Feeds</a>
                <a href="{{ route('admin.launch') }}" class="text-slate-600 hover:text-slate-900">Google</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.users.index') }}" class="text-slate-600 hover:text-slate-900">Users</a>
                    <a href="{{ route('admin.settings.edit') }}" class="text-slate-600 hover:text-slate-900">Settings</a>
                @endif
            </nav>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-500">{{ auth()->user()->name }} &middot; {{ auth()->user()->role ?? 'no role' }}</span>
                <a href="{{ route('admin.password.edit') }}" class="text-slate-600 hover:text-slate-900">Password</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-600 hover:text-slate-900">Log out</button>
                </form>
            </div>
        </div>
    </header>
    @endauth

    <main class="mx-auto max-w-6xl px-6 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
