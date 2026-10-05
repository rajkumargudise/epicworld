@extends('layouts.public')

@php
    $context = 'article';
@endphp

@section('content')
    <div id="read-progress" aria-hidden="true"></div>

    <article class="mx-auto max-w-3xl">
        <nav class="mb-5 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-accent">Home</a>
            @if ($article->category)
                <span aria-hidden="true">/</span>
                <a href="{{ route('category.show', $article->category) }}" class="hover:text-accent">{{ $article->category->name }}</a>
            @endif
        </nav>

        <h1 class="text-4xl font-extrabold leading-[1.08] tracking-tight sm:text-5xl">{{ $article->title }}</h1>

        @if ($article->displayExcerpt())
            <p class="mt-5 text-lg leading-relaxed text-ink-soft sm:text-xl">{{ $article->displayExcerpt() }}</p>
        @endif

        <div class="mt-6 flex flex-wrap items-center gap-2 border-y border-line py-4 text-sm text-muted">
            @if ($article->author)
                <span class="font-semibold text-ink">{{ $article->author->name }}</span>
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
            <img src="{{ $article->featured_image }}" alt="{{ $article->title }}" class="mt-8 w-full rounded-3xl border border-line object-cover">
        @endif

        <x-ad-slot name="article_top" :context="$context" />

        <div class="article-body mt-10">
            {!! $article->displayContentHtml() !!}
        </div>

        <x-ad-slot name="article_bottom" :context="$context" />

        @if ($article->tags->isNotEmpty())
            <div class="mt-10 flex flex-wrap gap-2">
                @foreach ($article->tags as $tag)
                    <a href="{{ route('tag.show', $tag) }}" class="chip rounded-full px-3 py-1 text-xs font-medium">#{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif

        @php
            $sources = $article->story?->sources ?? collect();
        @endphp

        @if ($sources->isNotEmpty())
            <div class="mt-8 rounded-2xl border border-line bg-surface p-5 text-sm text-muted">
                <span class="font-semibold text-ink">Sources:</span>
                @foreach ($sources as $source)
                    @if ($source->homepage_url)
                        <a href="{{ $source->homepage_url }}" rel="nofollow noopener" target="_blank" class="text-accent hover:underline">{{ $source->name }}</a>
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
        <section class="mx-auto mt-16 max-w-5xl border-t border-line pt-10">
            <h2 class="mb-6 text-2xl font-extrabold tracking-tight">Related articles</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($relatedArticles as $relatedArticle)
                    @include('partials.article-card', ['article' => $relatedArticle])
                @endforeach
            </div>
        </section>
    @endif
@endsection
