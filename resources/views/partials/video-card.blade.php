{{-- A playable video card (YouTube privacy-enhanced embed, loaded on click). Expects $video (WireItem, kind=video). --}}
<article class="card group overflow-hidden rounded-2xl">
    <div class="relative aspect-video bg-black" data-embed="https://www.youtube-nocookie.com/embed/{{ $video->video_id }}?autoplay=1&rel=0">
        <img src="{{ $video->thumbnail() }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-full w-full object-cover opacity-90 transition group-hover:opacity-100">
        <button type="button" data-embed-play class="absolute inset-0 grid place-items-center" aria-label="Play: {{ $video->title }}">
            <span class="grid h-14 w-14 place-items-center rounded-full bg-black/60 text-white backdrop-blur transition group-hover:scale-110" style="background: linear-gradient(135deg, var(--accent), var(--accent-2))">
                <svg class="ml-0.5 h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            </span>
        </button>
    </div>
    <div class="p-4">
        <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted">
            <span class="text-accent">{{ $video->source }}</span>
            <span>&middot;</span>
            <time datetime="{{ $video->published_at->toIso8601String() }}">{{ $video->published_at->diffForHumans() }}</time>
        </div>
        <h3 class="line-clamp-2 text-[0.95rem] font-bold leading-snug"><a href="{{ $video->path() }}" class="hover:text-accent">{{ $video->title }}</a></h3>
    </div>
</article>
