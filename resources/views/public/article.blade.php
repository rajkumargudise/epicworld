@extends('layouts.public')

@php
    $context = 'article';
@endphp

@section('content')
    <article class="mx-auto max-w-3xl">
        <nav class="mb-4 text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a>
            @if ($article->category)
                <span aria-hidden="true">/</span>
                <a href="{{ route('category.show', $article->category) }}" class="hover:text-brand-600">{{ $article->category->name }}</a>
            @endif
        </nav>

        <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-5xl sm:leading-[1.1]">{{ $article->title }}</h1>

        @if ($article->displayExcerpt())
            <p class="mt-5 text-lg leading-relaxed text-slate-600 sm:text-xl">{{ $article->displayExcerpt() }}</p>
        @endif

        <div class="mt-6 flex flex-wrap items-center gap-2 border-y border-slate-200 py-4 text-sm text-slate-500">
            @if ($article->author)
                <span class="font-semibold text-slate-700">{{ $article->author->name }}</span>
                <span aria-hidden="true">&middot;</span>
            @endif
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('M j, Y') }}</time>
            @endif
            @if ($article->updated_content_at && $article->published_at && ! $article->updated_content_at->equalTo($article->published_at))
                <span aria-hidden="true">&middot;</span>
                <span>
                    Updated
                    <time datetime="{{ $article->updated_content_at->toIso8601String() }}">{{ $article->updated_content_at->format('M j, Y') }}</time>
                </span>
            @endif
            <span aria-hidden="true">&middot;</span>
            <span>{{ $article->displayReadingTimeMinutes() }} min read</span>
        </div>

        @if ($article->featured_image)
            <img src="{{ $article->featured_image }}" alt="{{ $article->title }}" class="mt-8 w-full rounded-2xl object-cover shadow-sm">
        @endif

        <x-ad-slot name="article_top" :context="$context" />

        <div class="article-body mt-8">
            {!! $article->displayContentHtml() !!}
        </div>

        <x-ad-slot name="article_bottom" :context="$context" />

        @if ($article->tags->isNotEmpty())
            <div class="mt-10 flex flex-wrap gap-2">
                @foreach ($article->tags as $tag)
                    <a href="{{ route('tag.show', $tag) }}"
                       class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-brand-50 hover:text-brand-700 hover:ring-brand-100">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @php
            $sources = $article->story?->sources ?? collect();
        @endphp

        @if ($sources->isNotEmpty())
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
                <span class="font-semibold text-slate-800">Sources:</span>
                @foreach ($sources as $source)
                    @if ($source->homepage_url)
                        <a href="{{ $source->homepage_url }}" rel="nofollow noopener" target="_blank" class="text-brand-600 hover:underline">{{ $source->name }}</a>
                    @else
                        <span>{{ $source->name }}</span>
                    @endif
                    @if (! $loop->last)
                        <span aria-hidden="true">&middot;</span>
                    @endif
                @endforeach
            </div>
        @endif
    </article>

    @if ($relatedArticles->isNotEmpty())
        <section class="mx-auto mt-16 max-w-5xl border-t border-slate-200 pt-10">
            <h2 class="mb-5 text-2xl font-extrabold tracking-tight text-slate-900">Related articles</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($relatedArticles as $relatedArticle)
                    @include('partials.article-card', ['article' => $relatedArticle])
                @endforeach
            </div>
        </section>
    @endif
@endsection
