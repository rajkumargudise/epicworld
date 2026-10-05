@extends('layouts.public')

@php($context = 'home')

@section('content')
    @if ($breaking->isNotEmpty())
        <div class="mb-8 flex items-center gap-3 overflow-x-auto rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <span class="shrink-0 rounded-full bg-red-500 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider">Breaking</span>
            <div class="flex gap-6">
                @foreach ($breaking as $item)
                    <a href="{{ route('article.show', $item) }}" class="shrink-0 whitespace-nowrap text-slate-200 hover:text-white hover:underline">
                        {{ $item->title }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($featured)
        <section class="mb-14">
            <article class="group grid overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-2">
                <a href="{{ route('article.show', $featured) }}" class="block overflow-hidden" tabindex="-1" aria-hidden="true">
                    @if ($featured->featured_image)
                        <img src="{{ $featured->featured_image }}" alt="" class="h-64 w-full object-cover transition duration-700 group-hover:scale-105 md:h-full">
                    @else
                        <div class="h-64 w-full bg-gradient-to-br from-brand-500 via-brand-600 to-slate-900 md:h-full"></div>
                    @endif
                </a>
                <div class="flex flex-col justify-center gap-4 p-6 sm:p-10">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-brand-700">Featured</span>
                        @if ($featured->category)
                            <a href="{{ route('category.show', $featured->category) }}" class="text-xs font-semibold uppercase tracking-wider text-slate-500 hover:text-brand-600">
                                {{ $featured->category->name }}
                            </a>
                        @endif
                    </div>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-4xl">
                        <a href="{{ route('article.show', $featured) }}" class="hover:text-brand-700">{{ $featured->title }}</a>
                    </h1>
                    @if ($featured->displayExcerpt())
                        <p class="text-base leading-relaxed text-slate-600">{{ $featured->displayExcerpt() }}</p>
                    @endif
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        @if ($featured->published_at)
                            <time datetime="{{ $featured->published_at->toIso8601String() }}">{{ $featured->published_at->format('M j, Y') }}</time>
                            <span aria-hidden="true">&middot;</span>
                        @endif
                        <span>{{ $featured->displayReadingTimeMinutes() }} min read</span>
                    </div>
                    <a href="{{ route('article.show', $featured) }}" class="mt-1 inline-flex w-fit items-center gap-1 rounded-full bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                        Read story <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </article>
        </section>
    @endif

    @if ($latest->isNotEmpty())
        <section class="mb-14">
            <div class="mb-5 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Latest</h2>
                <a href="{{ route('latest') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">View all &rarr;</a>
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
        <section class="mb-14">
            <div class="mb-5 flex items-end justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $section['category']->name }}</h2>
                <a href="{{ route('category.show', $section['category']) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
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
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No articles have been published yet.</p>
    @endif
@endsection
