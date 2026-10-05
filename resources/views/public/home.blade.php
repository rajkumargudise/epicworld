@extends('layouts.public')

@php
    $context = 'home';
    $lead = $featured ?? $latest->first();
    $others = $latest->reject(fn ($a) => $lead && $a->id === $lead->id)->values();
    $topStories = $others->take(4);
    $moreLatest = $others->slice(4)->values();
    $wire = $breaking->merge($others)->unique('id')->take(12);
@endphp

@section('content')
    {{-- Masthead strip: date + live wire --}}
    <div class="mb-6 flex items-center gap-4 overflow-hidden rounded-full border border-line bg-surface px-4 py-2 text-sm">
        <span class="flex shrink-0 items-center gap-2 text-xs font-bold uppercase tracking-wider text-red-400">
            <span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span> Live wire
        </span>
        <span class="hidden shrink-0 text-xs text-muted sm:inline">{{ now()->format('l, j F Y') }}</span>
        @if ($wire->isNotEmpty())
            <div class="ticker relative min-w-0 flex-1 overflow-hidden">
                <div class="ticker-track gap-10">
                    @foreach ([1, 2] as $copy)
                        <div class="flex shrink-0 gap-10 pr-10" @if ($copy === 2) aria-hidden="true" @endif>
                            @foreach ($wire as $item)
                                <a href="{{ route('article.show', $item) }}" class="whitespace-nowrap text-ink-soft transition hover:text-accent" @if ($copy === 2) tabindex="-1" @endif>{{ $item->title }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($lead)
        <section class="mb-10 grid gap-5 lg:grid-cols-12">
            <div class="reveal lg:col-span-7">
                @include('partials.story-overlay', ['article' => $lead, 'tall' => true])
            </div>

            <aside class="reveal card rounded-3xl p-5 lg:col-span-5">
                <div class="mb-2 flex items-center justify-between px-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-muted">Top stories</h2>
                    <a href="{{ route('latest') }}" class="text-xs font-semibold text-accent hover:underline">All &rarr;</a>
                </div>
                <div class="divide-y divide-line">
                    @foreach ($topStories as $article)
                        @include('partials.story-compact', ['article' => $article, 'n' => $loop->iteration])
                    @endforeach
                </div>
            </aside>
        </section>
    @endif

    {{-- Topic chips --}}
    @if (($navCategories ?? collect())->isNotEmpty())
        <nav class="mb-12 flex gap-2 overflow-x-auto pb-1" aria-label="Browse topics">
            @foreach ($navCategories->reject(fn ($c) => $c->slug === 'latest') as $chipCategory)
                <a href="{{ route('category.show', $chipCategory) }}" class="chip shrink-0 rounded-full px-4 py-2 text-sm font-medium">{{ $chipCategory->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($moreLatest->isNotEmpty())
        <section class="mb-16">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Fresh from the newsroom</h2>
                <a href="{{ route('latest') }}" class="text-sm font-semibold text-accent hover:underline">View all &rarr;</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($moreLatest as $article)
                    @include('partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endif

    <x-ad-slot name="home_between_sections" :context="$context" />

    @foreach ($categorySections as $section)
        @php($sectionArticles = $section['articles']->values())
        @continue($sectionArticles->isEmpty())
        <section class="mb-16">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="flex items-center gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    <span class="h-7 w-1.5 rounded-full" style="background: linear-gradient(var(--accent), var(--accent-2))"></span>
                    {{ $section['category']->name }}
                </h2>
                <a href="{{ route('category.show', $section['category']) }}" class="text-sm font-semibold text-accent hover:underline">View all &rarr;</a>
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

    @if (! $lead)
        <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-muted">No articles have been published yet.</p>
    @endif
@endsection
