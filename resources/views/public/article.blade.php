@extends('layouts.public')

@php
    $context = 'article';
    $layout = $article->storyLayout();
    $sources = $article->story?->sources ?? collect();
    $shareUrl = route('article.show', $article);
@endphp

@section('content')
    <div id="read-progress" aria-hidden="true"></div>

    {{-- Story header --}}
    <header class="relative mb-10 overflow-hidden rounded-3xl border border-line">
        <div class="hero-glow absolute inset-0" aria-hidden="true"></div>
        <div class="relative px-6 py-10 sm:px-12 sm:py-14">
            <nav class="mb-5 flex items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-accent">Home</a>
                @if ($article->category)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('category.show', $article->category) }}" class="rounded-full bg-accent/15 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-accent">{{ $article->category->name }}</a>
                @endif
            </nav>
            <h1 class="max-w-4xl text-4xl font-extrabold leading-[1.06] tracking-tight sm:text-6xl">{{ $article->title }}</h1>
            @if ($article->displayExcerpt())
                <p class="mt-5 max-w-3xl text-lg leading-relaxed text-ink-soft sm:text-xl">{{ $article->displayExcerpt() }}</p>
            @endif
            <div class="mt-7 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                @if ($article->author)
                    <span class="font-semibold text-ink">{{ $article->author->name }}</span>
                    <span aria-hidden="true">&middot;</span>
                @endif
                @if ($article->published_at)
                    <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('M j, Y') }}</time>
                    <span aria-hidden="true">&middot;</span>
                @endif
                @if ($article->updated_content_at && $article->published_at && ! $article->updated_content_at->equalTo($article->published_at))
                    <span>Updated <time datetime="{{ $article->updated_content_at->toIso8601String() }}">{{ $article->updated_content_at->format('M j, Y') }}</time></span>
                    <span aria-hidden="true">&middot;</span>
                @endif
                <span>{{ $article->displayReadingTimeMinutes() }} min read</span>
            </div>
        </div>
    </header>

    <div class="grid gap-10 lg:grid-cols-[230px_minmax(0,1fr)]">
        {{-- Left rail: contents, share --}}
        <aside class="order-2 lg:order-1">
            <div class="lg:sticky lg:top-24 space-y-4">
                @if (count($layout['toc']) > 1)
                    <nav class="story-card hidden rounded-2xl p-5 lg:block" aria-label="In this story">
                        <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted">In this story</h2>
                        <ol class="space-y-1" id="story-toc">
                            @foreach ($layout['toc'] as $item)
                                <li>
                                    <a href="#{{ $item['id'] }}" data-toc-link="{{ $item['id'] }}" class="flex gap-2.5 rounded-lg px-2 py-1.5 text-sm text-muted transition hover:text-ink">
                                        <span class="font-mono text-xs leading-5 text-accent">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="leading-5">{{ $item['title'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @endif

                <div class="story-card rounded-2xl p-5">
                    <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted">At a glance</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Read</dt><dd class="font-medium">{{ $article->displayReadingTimeMinutes() }} min</dd></div>
                        @if ($article->category)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Topic</dt><dd class="font-medium">{{ $article->category->name }}</dd></div>
                        @endif
                        @if ($sources->isNotEmpty())
                            <div class="flex justify-between gap-3"><dt class="text-muted">Sources</dt><dd class="font-medium">{{ $sources->count() }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="story-card rounded-2xl p-5">
                    <h2 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted">Share</h2>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <a class="chip rounded-full px-3 py-1.5" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode($article->title) }}">X</a>
                        <a class="chip rounded-full px-3 py-1.5" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($article->title.' '.$shareUrl) }}">WhatsApp</a>
                        <a class="chip rounded-full px-3 py-1.5" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($shareUrl) }}">LinkedIn</a>
                        <button type="button" data-copy-link="{{ $shareUrl }}" class="chip rounded-full px-3 py-1.5">Copy link</button>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Story body --}}
        <article class="order-1 min-w-0 lg:order-2">
            @if ($article->featured_image)
                <img src="{{ $article->featured_image }}" alt="{{ $article->title }}" class="mb-8 w-full rounded-3xl border border-line object-cover">
            @endif

            @if (count($layout['takeaways']))
                <section class="takeaways mb-8 rounded-3xl p-6 sm:p-8" aria-labelledby="takeaways-h">
                    <h2 id="takeaways-h" class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-accent">
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-accent/15">&#9889;</span> Key takeaways
                    </h2>
                    <ul class="space-y-3">
                        @foreach ($layout['takeaways'] as $point)
                            <li class="flex gap-3 text-[1.02rem] leading-relaxed text-ink">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                                <span>{!! $point !!}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <x-ad-slot name="article_top" :context="$context" />

            @if ($layout['lead'])
                <p class="mb-8 text-xl leading-relaxed text-ink sm:text-[1.4rem] sm:leading-[1.65]">{!! $layout['lead'] !!}</p>
            @endif

            <div class="space-y-6">
                @foreach ($layout['sections'] as $section)
                    <section id="{{ $section['id'] }}" data-story-section class="story-card section-{{ $section['variant'] }} scroll-mt-24 rounded-3xl p-6 sm:p-9">
                        @if ($section['title'] !== '')
                            <header class="mb-5 flex items-center gap-4">
                                <span class="font-mono text-sm font-bold text-accent">{{ $section['number'] }}</span>
                                <span class="h-px flex-1 bg-line"></span>
                            </header>
                            <h2 class="mb-5 text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">
                                @if ($section['variant'] === 'why') <span aria-hidden="true">💡</span> @elseif ($section['variant'] === 'next') <span aria-hidden="true">🔭</span> @elseif ($section['variant'] === 'context') <span aria-hidden="true">📚</span> @endif
                                {{ $section['title'] }}
                            </h2>
                        @endif

                        <div class="story-prose space-y-5">
                            @foreach ($section['blocks'] as $block)
                                @switch($block['type'])
                                    @case('p')
                                        <p>{!! $block['html'] !!}</p>
                                        @break
                                    @case('quote')
                                        <blockquote class="pullquote">{!! $block['html'] !!}</blockquote>
                                        @break
                                    @case('ul')
                                        <ul class="checklist">
                                            @foreach ($block['items'] as $item)
                                                <li>{!! $item !!}</li>
                                            @endforeach
                                        </ul>
                                        @break
                                    @case('ol')
                                        <ol class="numbered">
                                            @foreach ($block['items'] as $item)
                                                <li>{!! $item !!}</li>
                                            @endforeach
                                        </ol>
                                        @break
                                @endswitch
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <x-ad-slot name="article_bottom" :context="$context" />

            @if ($article->tags->isNotEmpty())
                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach ($article->tags as $tag)
                        <a href="{{ route('tag.show', $tag) }}" class="chip rounded-full px-3 py-1 text-xs font-medium">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif

            @if ($sources->isNotEmpty())
                <section class="story-card mt-8 rounded-3xl p-6 sm:p-8" aria-labelledby="sources-h">
                    <h2 id="sources-h" class="mb-1 text-xs font-bold uppercase tracking-wider text-muted">Sources &amp; credits</h2>
                    <p class="mb-4 text-sm text-muted">This report was written by EPIC World from the reporting below. Original reporting belongs to its publishers.</p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach ($sources as $source)
                            <li class="rounded-xl border border-line bg-surface-2 px-4 py-3 text-sm">
                                @if ($source->homepage_url)
                                    <a href="{{ $source->homepage_url }}" rel="nofollow noopener" target="_blank" class="font-semibold text-ink hover:text-accent">{{ $source->name }} <span aria-hidden="true">&#8599;</span></a>
                                @else
                                    <span class="font-semibold">{{ $source->name }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </article>
    </div>

    @if ($relatedArticles->isNotEmpty())
        <section class="mt-16 border-t border-line pt-10">
            <h2 class="mb-6 text-2xl font-extrabold tracking-tight">Related articles</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($relatedArticles as $relatedArticle)
                    @include('partials.article-card', ['article' => $relatedArticle])
                @endforeach
            </div>
        </section>
    @endif
@endsection
