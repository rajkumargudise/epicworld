{{-- A single wire headline row. Expects $item (WireItem). --}}
<article class="group relative flex gap-4 rounded-2xl p-3 transition hover:bg-surface-2">
    <div class="min-w-0 flex-1">
        <div class="mb-1 flex flex-wrap items-center gap-x-2 text-[11px] font-semibold uppercase tracking-wider text-muted">
            <span class="text-accent">{{ $item->source }}</span>
            <span>&middot;</span>
            <time datetime="{{ $item->published_at->toIso8601String() }}">{{ $item->published_at->diffForHumans() }}</time>
        </div>
        <h3 class="text-[0.98rem] font-bold leading-snug tracking-tight">
            <a href="{{ $item->path() }}" class="after:absolute after:inset-0 group-hover:text-accent">{{ $item->title }}</a>
        </h3>
        @if (($showSummary ?? false) && $item->summary)
            <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-muted">{{ $item->summary }}</p>
        @endif
    </div>
    @if ($item->image_url)
        <img src="{{ $item->image_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-16 w-24 shrink-0 rounded-xl object-cover">
    @endif
</article>
