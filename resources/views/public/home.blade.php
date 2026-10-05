@extends('layouts.public')

@php($context = 'home')

@section('content')
    @if ($breaking->isNotEmpty())
        <div class="mb-8 flex items-center gap-3 overflow-x-auto rounded-full border border-line bg-surface px-4 py-2.5 text-sm">
            <span class="flex shrink-0 items-center gap-1.5 rounded-full bg-red-500/15 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-red-400">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span> Breaking
            </span>
            <div class="flex gap-6">
                @foreach ($breaking as $item)
                    <a href="{{ route('article.show', $item) }}" class="shrink-0 whitespace-nowrap text-ink-soft transition hover:text-accent">{{ $item->title }}</a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Hero --}}
    <section class="relative -mx-4 mb-14 overflow-hidden rounded-3xl border border-line sm:mx-0">
        <div class="hero-glow absolute inset-0" aria-hidden="true"></div>
        <div class="relative px-6 py-12 sm:px-12 sm:py-16">
            <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3 py-1 text-xs font-medium text-ink-soft backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span> Tech &middot; AI &middot; Business &middot; Science
            </p>
            <h1 class="max-w-3xl text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-6xl">
                Stories that move the world, <span class="gradient-text">made clear.</span>
            </h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-ink-soft sm:text-lg">
                Sharp, sourced coverage and explainers on the ideas shaping technology, business and science.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('latest') }}" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Read the latest</a>
                <a href="{{ route('search') }}" class="chip rounded-full px-6 py-3 text-sm font-semibold">Search</a>
            </div>
        </div>
    </section>

    @if ($featured)
        <section class="mb-16">
            <article class="card reveal group grid overflow-hidden rounded-3xl md:grid-cols-5">
                <a href="{{ route('article.show', $featured) }}" class="block overflow-hidden md:col-span-3" tabindex="-1" aria-hidden="true">
                    @if ($featured->featured_image)
                        <img src="{{ $featured->featured_image }}" alt="" class="h-64 w-full object-cover transition duration-700 group-hover:scale-105 md:h-full">
                    @else
                        <div class="placeholder-art h-64 w-full md:h-full"></div>
                    @endif
                </a>
                <div class="flex flex-col justify-center gap-4 p-6 sm:p-10 md:col-span-2">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white" style="background: linear-gradient(100deg, var(--accent), var(--accent-2))">Featured</span>
                        @if ($featured->category)
                            <a href="{{ route('category.show', $featured->category) }}" class="text-xs font-semibold uppercase tracking-wider text-muted hover:text-accent">{{ $featured->category->name }}</a>
                        @endif
                    </div>
                    <h2 class="text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">
                        <a href="{{ route('article.show', $featured) }}" class="transition hover:text-accent">{{ $featured->title }}</a>
                    </h2>
                    @if ($featured->displayExcerpt())
                        <p class="leading-relaxed text-ink-soft">{{ $featured->displayExcerpt() }}</p>
                    @endif
                    <div class="flex items-center gap-2 text-xs text-muted">
                        @if ($featured->published_at)
                            <time datetime="{{ $featured->published_at->toIso8601String() }}">{{ $featured->published_at->format('M j, Y') }}</time>
                            <span aria-hidden="true">&middot;</span>
                        @endif
                        <span>{{ $featured->displayReadingTimeMinutes() }} min read</span>
                    </div>
                    <a href="{{ route('article.show', $featured) }}" class="text-sm font-semibold text-accent hover:underline">Read story &rarr;</a>
                </div>
            </article>
        </section>
    @endif

    @if ($latest->isNotEmpty())
        <section class="mb-16">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Latest</h2>
                <a href="{{ route('latest') }}" class="text-sm font-semibold text-accent hover:underline">View all &rarr;</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($latest as $article)
                    @include('partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endif

    <x-ad-slot name="home_between_sections" :context="$context" />

    @foreach ($categorySections as $section)
        <section class="mb-16">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $section['category']->name }}</h2>
                <a href="{{ route('category.show', $section['category']) }}" class="text-sm font-semibold text-accent hover:underline">View all &rarr;</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($section['articles'] as $article)
                    @include('partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($latest->isEmpty() && ! $featured)
        <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-muted">No articles have been published yet.</p>
    @endif
@endsection
