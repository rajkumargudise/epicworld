@extends('layouts.public')

@php($context = 'live')

@section('content')
    <article class="mx-auto max-w-3xl">
        <nav class="mb-5 flex items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('live') }}" class="hover:text-accent">Live desk</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('live.scope', $item->kind === 'video' ? 'videos' : $item->scope) }}" class="rounded-full bg-accent/15 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-accent">
                {{ $item->kind === 'video' ? 'Video' : config('newswire.scopes.'.$item->scope.'.label') }}
            </a>
        </nav>

        <h1 class="text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl">{{ $item->title }}</h1>

        <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
            <span class="font-semibold text-ink">{{ $item->source }}</span>
            <span aria-hidden="true">&middot;</span>
            <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->format('M j, Y · g:i A') }} ({{ $item->published_at->diffForHumans() }})</time>
        </div>

        @if ($item->isVideo())
            <div class="mt-8 overflow-hidden rounded-3xl border border-line">
                <div class="relative aspect-video bg-black" data-embed="https://www.youtube-nocookie.com/embed/{{ $item->video_id }}?autoplay=1&rel=0">
                    <img src="{{ $item->thumbnail() }}" alt="" referrerpolicy="no-referrer" class="h-full w-full object-cover opacity-90">
                    <button type="button" data-embed-play class="absolute inset-0 grid place-items-center" aria-label="Play video">
                        <span class="grid h-20 w-20 place-items-center rounded-full text-white" style="background: linear-gradient(135deg, var(--accent), var(--accent-2))">
                            <svg class="ml-1 h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </button>
                </div>
            </div>
        @elseif ($item->image_url)
            <img src="{{ $item->image_url }}" alt="" referrerpolicy="no-referrer" class="mt-8 w-full rounded-3xl border border-line object-cover">
        @endif

        @if ($item->summary)
            <div class="story-card mt-8 rounded-3xl p-6 sm:p-8">
                <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-accent">The brief</h2>
                <p class="text-lg leading-relaxed text-ink">{{ $item->summary }}</p>
            </div>
        @endif

        @can('access-admin')
            @if ($item->kind === 'article')
                <form method="POST" action="{{ route('admin.wire.write', $item) }}" class="story-card mt-6 flex flex-wrap items-center justify-between gap-3 rounded-3xl p-5"
                      onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Writing… about a minute';">
                    @csrf
                    <p class="text-sm text-ink-soft">Editor tool: turn this headline into a full original article (saved as a draft for review).</p>
                    <button class="btn-primary rounded-full px-5 py-2.5 text-sm font-semibold">Write full article</button>
                </form>
            @endif
        @endcan

        <div class="story-card mt-6 flex flex-wrap items-center justify-between gap-4 rounded-3xl p-6">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-muted">Reported by</p>
                <p class="text-lg font-bold">{{ $item->source }}</p>
                <p class="mt-1 text-sm text-muted">This is a short headline brief. The complete report belongs to its publisher.</p>
            </div>
            <a href="{{ $item->url }}" target="_blank" rel="nofollow noopener" class="btn-primary shrink-0 rounded-full px-6 py-3 text-sm font-semibold">
                {{ $item->kind === 'video' ? 'Watch on source' : 'Read full report' }} &#8599;
            </a>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-14 max-w-5xl border-t border-line pt-10">
            <h2 class="mb-5 text-2xl font-extrabold tracking-tight">More {{ $item->kind === 'video' ? 'videos' : 'headlines' }}</h2>
            @if ($item->kind === 'video')
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $video)
                        @include('partials.video-card', ['video' => $video])
                    @endforeach
                </div>
            @else
                <div class="card rounded-3xl p-2"><div class="divide-y divide-line">
                    @foreach ($related as $row)
                        @include('live._item', ['item' => $row])
                    @endforeach
                </div></div>
            @endif
        </section>
    @endif
@endsection
