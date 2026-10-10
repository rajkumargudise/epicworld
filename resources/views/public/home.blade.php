@extends('layouts.public')

@php
    $context = 'home';
    $lead = $featured ?? $latest->first();
    $others = $latest->reject(fn ($a) => $lead && $a->id === $lead->id)->values();
    $topStories = $others->take(4);
    $moreLatest = $others->slice(4)->values();
    $scopeMeta = config('newswire.scopes');
    $hasWire = collect($wire)->flatten()->isNotEmpty();
    // India first, then a few world headlines - the ticker is India-led.
    $tickerItems = collect($wire['news'] ?? [])->take(10)->merge(collect($wire['world'] ?? [])->take(4))->unique('id')->values();
    $tickerSeconds = max(60, $tickerItems->count() * 11);
@endphp

@section('content')
    {{-- Live strip: date + scrolling headlines --}}
    <div class="mb-6 flex items-center gap-4 overflow-hidden rounded-full border border-line bg-surface px-4 py-2 text-sm">
        <span class="flex shrink-0 items-center gap-2 text-xs font-bold uppercase tracking-wider text-red-400">
            <span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span> Live
        </span>
        <span class="hidden shrink-0 text-xs text-muted sm:inline">{{ now()->format('l, j F Y') }}</span>
        @if ($tickerItems->isNotEmpty())
            <div class="ticker relative min-w-0 flex-1 overflow-hidden">
                <div class="ticker-track gap-10" style="--ticker-duration: {{ $tickerSeconds }}s">
                    @foreach ([1, 2] as $copy)
                        <div class="flex shrink-0 gap-10 pr-10" @if ($copy === 2) aria-hidden="true" @endif>
                            @foreach ($tickerItems as $item)
                                <a href="{{ $item->path() }}" class="whitespace-nowrap text-ink-soft transition hover:text-accent" @if ($copy === 2) tabindex="-1" @endif>
                                    <span class="text-accent">{{ $item->source }}</span> {{ $item->title }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- INDIA NOW: hot stories + top India headlines --}}
    @if ($hotStories->isNotEmpty() || $indiaHeadlines->isNotEmpty())
        <section class="mb-9" aria-labelledby="india-now">
            <div class="mb-4 flex items-end justify-between">
                <h2 id="india-now" class="flex items-center gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <span class="h-7 w-1.5 rounded-full" style="background: linear-gradient(#ff9933, #138808)"></span> India now
                </h2>
                <a href="{{ route('live.scope', 'news') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent hover:underline">All India news &rarr;</a>
            </div>
            <div class="grid gap-5 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <h3 class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted"><span aria-hidden="true">🔥</span> Hot stories &mdash; covered by several outlets</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($hotStories as $hot)
                            <article class="card group relative flex flex-col gap-2 rounded-2xl p-5">
                                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider">
                                    <span class="rounded-full bg-orange-500/15 px-2 py-0.5 text-orange-400">{{ $hot['count'] }} outlets</span>
                                    <time class="text-muted" datetime="{{ $hot['lead']->published_at->toIso8601String() }}">{{ $hot['lead']->published_at->diffForHumans() }}</time>
                                </div>
                                <h4 class="text-[1.02rem] font-bold leading-snug tracking-tight">
                                    <a href="{{ $hot['lead']->path() }}" class="after:absolute after:inset-0 group-hover:text-accent">{{ $hot['lead']->title }}</a>
                                </h4>
                                <p class="mt-auto text-xs text-muted">{{ collect($hot['sources'])->take(4)->implode(' · ') }}</p>
                            </article>
                        @endforeach
                        @if ($hotStories->isEmpty())
                            <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-6 text-sm text-muted sm:col-span-2">Hot stories appear here when several newsrooms report the same development.</p>
                        @endif
                    </div>
                </div>
                <aside class="card rounded-3xl p-3 lg:col-span-5">
                    <h3 class="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wider text-muted">Top India headlines</h3>
                    <div class="divide-y divide-line" data-live-poll="{{ route('live.feed', ['scope' => 'news', 'variant' => 'list']) }}">
                        @include('live._list', ['items' => $indiaHeadlines])
                    </div>
                </aside>
            </div>
        </section>
    @endif

    {{-- FEATURED GUIDES: the long, in-depth reads --}}
    @if ($guides->isNotEmpty())
        <section class="mb-9" aria-labelledby="guides-h">
            <div class="mb-4 flex items-end justify-between">
                <h2 id="guides-h" class="flex items-center gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <span class="h-7 w-1.5 rounded-full" style="background: linear-gradient(var(--accent), var(--accent-2))"></span> Featured guides
                </h2>
                <a href="{{ route('latest') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent hover:underline">More guides &rarr;</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($guides as $guide)
                    @include('partials.article-card', ['article' => $guide])
                @endforeach
            </div>
        </section>
    @endif

    {{-- LIVE NEWS: India / World / Local --}}
    <section class="mb-9" data-tabs>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <h1 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                <span class="h-8 w-1.5 rounded-full bg-red-500"></span> Live news
            </h1>
            <div class="flex items-center gap-2" role="tablist" aria-label="Live news sections">
                @foreach ($scopeMeta as $key => $meta)
                    <a href="{{ route('live.scope', $key) }}" role="tab" data-tab="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                       class="chip rounded-full px-4 py-3 text-sm font-semibold {{ $loop->first ? '!border-accent !text-ink' : '' }}">{{ $meta['label'] }}</a>
                @endforeach
                <a href="{{ route('live') }}" class="ml-1 hidden text-sm font-semibold text-accent hover:underline sm:inline">Live desk &rarr;</a>
            </div>
        </div>

        @foreach ($scopeMeta as $key => $meta)
            <div data-tab-panel="{{ $key }}" class="{{ $loop->first ? '' : 'hidden' }}">
                <div data-live-poll="{{ route('live.feed', ['scope' => $key, 'variant' => 'panel']) }}" data-live-swap="panel">
                    @include('live._panel', ['items' => $wire[$key] ?? collect()])
                </div>
            </div>
        @endforeach
    </section>

    {{-- LIVE TV & VIDEO --}}
    <section class="mb-9">
        <div class="mb-4 flex items-end justify-between">
            <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                <span class="h-7 w-1.5 rounded-full bg-red-500"></span> Live TV &amp; video
            </h2>
            <a href="{{ route('live.scope', 'videos') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent hover:underline">All video &rarr;</a>
        </div>
        @include('partials.live-tv', ['channels' => array_slice(config('newswire.live_channels'), 0, 4)])

        @if ($videos->isNotEmpty())
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($videos->take(4) as $video)
                    @include('partials.video-card', ['video' => $video])
                @endforeach
            </div>
        @endif
    </section>

    {{-- EPIC World's own reporting --}}
    @if ($lead)
        <div class="mb-4 flex items-end justify-between">
            <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                <span class="h-7 w-1.5 rounded-full" style="background: linear-gradient(var(--accent), var(--accent-2))"></span> Stories &amp; explainers
            </h2>
            <a href="{{ route('latest') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent hover:underline">All stories &rarr;</a>
        </div>

        <section class="mb-8 grid gap-5 lg:grid-cols-12">
            <div class="reveal lg:col-span-7">
                @include('partials.story-overlay', ['article' => $lead, 'tall' => true])
            </div>

            <aside class="reveal card rounded-3xl p-5 lg:col-span-5">
                <div class="mb-2 px-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Top stories</h3>
                </div>
                <div class="divide-y divide-line">
                    @foreach ($topStories as $article)
                        @include('partials.story-compact', ['article' => $article, 'n' => $loop->iteration])
                    @endforeach
                </div>
            </aside>
        </section>
    @endif

    @if (($navCategories ?? collect())->isNotEmpty())
        <nav class="mb-8 flex gap-2 overflow-x-auto pb-1" aria-label="Browse topics">
            @foreach ($navCategories->reject(fn ($c) => $c->slug === 'latest') as $chipCategory)
                <a href="{{ route('category.show', $chipCategory) }}" class="chip shrink-0 rounded-full px-4 py-3 text-sm font-medium">{{ $chipCategory->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($moreLatest->isNotEmpty())
        <section class="mb-9">
            <div class="mb-4 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Fresh from the newsroom</h2>
                <a href="{{ route('latest') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent hover:underline">View all &rarr;</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($moreLatest as $article)
                    @include('partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endif

    <div class="mb-9">@include('partials.newsletter', ['source' => 'home'])</div>

    <x-ad-slot name="home_between_sections" :context="$context" />

    @foreach ($categorySections as $section)
        @php($sectionArticles = $section['articles']->values())
        @continue($sectionArticles->isEmpty())
        <section class="mb-9">
            <div class="mb-4 flex items-end justify-between">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <span class="h-7 w-1.5 rounded-full" style="background: linear-gradient(var(--accent), var(--accent-2))"></span>
                    {{ $section['category']->name }}
                </h2>
                <a href="{{ route('category.show', $section['category']) }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent hover:underline">View all &rarr;</a>
            </div>
            <div class="grid gap-5 lg:grid-cols-12">
                <div class="reveal lg:col-span-7">
                    @include('partials.story-overlay', ['article' => $sectionArticles->first()])
                </div>
                <div class="reveal card rounded-3xl p-3 lg:col-span-5">
                    <div class="divide-y divide-line">
                        @foreach ($sectionArticles->slice(1) as $article)
                            @include('partials.story-compact', ['article' => $article])
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endforeach
@endsection
