@extends('layouts.public')

@php
    $context = 'live';
    $tabs = [['world', 'World'], ['news', 'News'], ['local', 'Local'], ['videos', 'Videos']];
    $active = $mode === 'scope' ? $scope : null;
    $isVideosPage = $mode === 'scope' && $scope === 'videos';
@endphp

@section('content')

    <header class="relative mb-8 overflow-hidden rounded-3xl border border-line px-6 py-10 sm:px-10">
        <div class="hero-glow absolute inset-0" aria-hidden="true"></div>
        <div class="relative">
            <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-red-500/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span> Live desk
            </p>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">
                {{ $mode === 'hub' ? 'News as it happens' : $meta['label'] }}
            </h1>
            <p class="mt-3 max-w-2xl text-ink-soft">{{ $mode === 'hub' ? 'World, India and local headlines plus live TV and video, refreshed every few minutes.' : $meta['blurb'] }}</p>
            <nav class="mt-6 flex flex-wrap gap-2" aria-label="Live desk sections">
                <a href="{{ route('live') }}" class="chip rounded-full px-4 py-2 text-sm font-semibold {{ $mode === 'hub' ? '!border-accent !text-ink' : '' }}">All</a>
                @foreach ($tabs as [$key, $label])
                    <a href="{{ route('live.scope', $key) }}" class="chip rounded-full px-4 py-2 text-sm font-semibold {{ $active === $key ? '!border-accent !text-ink' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    @if (count($channels))
        <section class="mb-12">
            <h2 class="mb-5 flex items-center gap-3 text-2xl font-extrabold tracking-tight">
                <span class="h-6 w-1.5 rounded-full bg-red-500"></span> Live TV
            </h2>
            @include('partials.live-tv', ['channels' => $mode === 'hub' ? array_slice($channels, 0, 8) : $channels])
        </section>
    @endif

    @if ($mode === 'hub')
        <div class="mb-12 grid gap-8 lg:grid-cols-3">
            @foreach ($sections as $section)
                <section>
                    <div class="mb-4 flex items-end justify-between">
                        <h2 class="text-2xl font-extrabold tracking-tight">{{ $section['meta']['label'] }}</h2>
                        <a href="{{ route('live.scope', $section['key']) }}" class="text-sm font-semibold text-accent hover:underline">More &rarr;</a>
                    </div>
                    <div class="card rounded-3xl p-2">
                        <div class="divide-y divide-line" data-live-poll="{{ route('live.feed', ['scope' => $section['key'], 'variant' => 'list']) }}">
                            @include('live._list', ['items' => $section['items']])
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @else
        @if ($scope !== 'videos')
            <section class="mb-12">
                <div class="card rounded-3xl p-2">
                    <div class="divide-y divide-line" @if ($items->currentPage() === 1) data-live-poll="{{ route('live.feed', ['scope' => $scope, 'variant' => 'list']) }}" @endif>
                        @include('live._list', ['items' => $items])
                    </div>
                </div>
                <div class="mt-8">{{ $items->links() }}</div>
            </section>
        @endif
    @endif

    @if ($videos->isNotEmpty() || ($mode === 'scope' && $scope === 'videos'))
        <section class="mb-12">
            <h2 class="mb-5 flex items-center gap-3 text-2xl font-extrabold tracking-tight">
                <span class="h-6 w-1.5 rounded-full" style="background: linear-gradient(var(--accent), var(--accent-2))"></span> Latest videos
            </h2>
            @php($videoList = ($mode === 'scope' && $scope === 'videos') ? $items : $videos)
            @if ($videoList->isEmpty())
                <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-8 text-center text-sm text-muted">Latest video reports will appear here shortly. Live TV above is available now.</p>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($videoList as $video)
                        @include('partials.video-card', ['video' => $video])
                    @endforeach
                </div>
                @if ($mode === 'scope' && $scope === 'videos')
                    <div class="mt-8">{{ $items->links() }}</div>
                @endif
            @endif
        </section>
    @endif

    <p class="text-xs text-muted">Headlines and videos are credited to and link back to their publishers. EPIC World's own reporting is under <a class="underline hover:text-accent" href="{{ route('latest') }}">Stories</a>.</p>
@endsection
