@extends('layouts.public')

@section('content')
    <article class="mx-auto max-w-3xl">
        @if ($article->category)
            <a href="{{ route('category.show', $article->category) }}"
               class="text-xs font-semibold uppercase tracking-wide text-indigo-600 hover:text-indigo-800">
                {{ $article->category->name }}
            </a>
        @endif

        <h1 class="mt-2 text-3xl font-bold leading-tight text-slate-900 sm:text-4xl">{{ $article->title }}</h1>

        @if ($article->displayExcerpt())
            <p class="mt-4 text-lg text-slate-600">{{ $article->displayExcerpt() }}</p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-2 border-b border-slate-200 pb-4 text-sm text-slate-500">
            @if ($article->author)
                <span class="font-medium text-slate-700">{{ $article->author->name }}</span>
                <span aria-hidden="true">&middot;</span>
            @endif
            @if ($article->published_at)
                <span>
                    Published
                    <time datetime="{{ $article->published_at->toIso8601String() }}">
                        {{ $article->published_at->format('M j, Y \a\t g:i A') }}
                    </time>
                </span>
            @endif
            @if ($article->updated_content_at && $article->published_at && ! $article->updated_content_at->equalTo($article->published_at))
                <span aria-hidden="true">&middot;</span>
                <span>
                    Updated
                    <time datetime="{{ $article->updated_content_at->toIso8601String() }}">
                        {{ $article->updated_content_at->format('M j, Y \a\t g:i A') }}
                    </time>
                </span>
            @endif
            <span aria-hidden="true">&middot;</span>
            <span>{{ $article->displayReadingTimeMinutes() }} min read</span>
        </div>

        @if ($article->featured_image)
            <img src="{{ $article->featured_image }}" alt="{{ $article->title }}" class="mt-6 w-full rounded-lg object-cover">
        @endif

        @include('partials.ad-slot', ['slot' => 'article-top'])

        <div class="prose prose-slate mt-6 max-w-none">
            {!! $article->content !!}
        </div>

        @include('partials.ad-slot', ['slot' => 'article-bottom'])

        @if ($article->tags->isNotEmpty())
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach ($article->tags as $tag)
                    <a href="{{ route('tag.show', $tag) }}"
                       class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-200">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif

        @php
            $sources = $article->story?->sources ?? collect();
        @endphp

        @if ($sources->isNotEmpty())
            <div class="mt-8 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <span class="font-semibold text-slate-700">Sources:</span>
                @foreach ($sources as $source)
                    @if ($source->homepage_url)
                        <a href="{{ $source->homepage_url }}" rel="nofollow noopener" target="_blank" class="text-indigo-600 hover:underline">{{ $source->name }}</a>
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
        <section class="mx-auto mt-12 max-w-3xl border-t border-slate-200 pt-8">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Related articles</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach ($relatedArticles as $relatedArticle)
                    @include('partials.article-card', ['article' => $relatedArticle])
                @endforeach
            </div>
        </section>
    @endif
@endsection
