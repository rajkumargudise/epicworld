@extends('layouts.public')

@php($context = 'home')

@section('content')
    @if ($breaking->isNotEmpty())
        <div class="mb-8 flex items-center gap-3 overflow-x-auto rounded-lg bg-slate-900 px-4 py-3 text-sm text-white">
            <span class="shrink-0 rounded bg-red-600 px-2 py-0.5 text-xs font-bold uppercase tracking-wide">Breaking</span>
            <div class="flex gap-6">
                @foreach ($breaking as $item)
                    <a href="{{ route('article.show', $item) }}" class="shrink-0 whitespace-nowrap hover:underline">
                        {{ $item->title }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($featured)
        <section class="mb-10">
            <article class="grid gap-6 overflow-hidden rounded-xl border border-slate-200 md:grid-cols-2">
                <a href="{{ route('article.show', $featured) }}" class="block">
                    @if ($featured->featured_image)
                        <img src="{{ $featured->featured_image }}" alt="{{ $featured->title }}" class="h-64 w-full object-cover md:h-full">
                    @else
                        <div class="h-64 w-full bg-slate-100 md:h-full"></div>
                    @endif
                </a>
                <div class="flex flex-col justify-center gap-3 p-6">
                    @if ($featured->category)
                        <a href="{{ route('category.show', $featured->category) }}"
                           class="text-xs font-semibold uppercase tracking-wide text-indigo-600 hover:text-indigo-800">
                            {{ $featured->category->name }}
                        </a>
                    @endif
                    <h1 class="text-2xl font-bold leading-tight text-slate-900 sm:text-3xl">
                        <a href="{{ route('article.show', $featured) }}" class="hover:underline">{{ $featured->title }}</a>
                    </h1>
                    @if ($featured->displayExcerpt())
                        <p class="text-slate-600">{{ $featured->displayExcerpt() }}</p>
                    @endif
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        @if ($featured->author)
                            <span>{{ $featured->author->name }}</span>
                            <span aria-hidden="true">&middot;</span>
                        @endif
                        @if ($featured->published_at)
                            <time datetime="{{ $featured->published_at->toIso8601String() }}">
                                {{ $featured->published_at->diffForHumans() }}
                            </time>
                        @endif
                        <span aria-hidden="true">&middot;</span>
                        <span>{{ $featured->displayReadingTimeMinutes() }} min read</span>
                    </div>
                </div>
            </article>
        </section>
    @endif

    @if ($latest->isNotEmpty())
        <section class="mb-12">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Latest</h2>
                <a href="{{ route('latest') }}" class="text-sm font-medium text-indigo-600 hover:underline">View all &rarr;</a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($latest as $article)
                    @include('partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endif

    <x-ad-slot name="home_between_sections" :context="$context" />

    @foreach ($categorySections as $section)
        <section class="mb-12">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">{{ $section['category']->name }}</h2>
                <a href="{{ route('category.show', $section['category']) }}" class="text-sm font-medium text-indigo-600 hover:underline">
                    View all &rarr;
                </a>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($section['articles'] as $article)
                    @include('partials.article-card', ['article' => $article])
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($latest->isEmpty() && ! $featured)
        <p class="text-slate-500">No articles have been published yet.</p>
    @endif
@endsection
