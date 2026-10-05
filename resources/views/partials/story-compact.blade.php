{{-- Compact list row. Expects $article; optional $n (rank number). --}}
<article class="group relative flex gap-4 rounded-2xl p-3 transition hover:bg-surface-2">
    @isset($n)
        <span class="font-mono text-2xl font-extrabold leading-none text-accent/80">{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</span>
    @endisset
    <div class="min-w-0 flex-1">
        <div class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted">
            @if ($article->category)<span class="text-accent">{{ $article->category->name }}</span>@endif
            @if ($article->published_at)<span>&middot; {{ $article->published_at->diffForHumans(null, true) }}</span>@endif
        </div>
        <h3 class="text-[0.98rem] font-bold leading-snug tracking-tight">
            <a href="{{ route('article.show', $article) }}" class="after:absolute after:inset-0 group-hover:text-accent">{{ $article->title }}</a>
        </h3>
    </div>
    @if ($article->featured_image)
        <img src="{{ $article->featured_image }}" alt="" loading="lazy" class="h-16 w-20 shrink-0 rounded-xl object-cover">
    @endif
</article>
