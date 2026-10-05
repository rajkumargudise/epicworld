{{-- Expects $article (with category and author eager-loaded). --}}
<article class="card reveal group flex flex-col overflow-hidden rounded-2xl">
    <a href="{{ route('article.show', $article) }}" class="block overflow-hidden" tabindex="-1" aria-hidden="true">
        @if ($article->featured_image)
            <img src="{{ $article->featured_image }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="placeholder-art aspect-[16/10] w-full"></div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-5">
        @if ($article->category)
            <a href="{{ route('category.show', $article->category) }}" class="relative z-10 -my-2 inline-block py-2 text-xs font-semibold uppercase tracking-wider text-accent hover:underline">
                {{ $article->category->name }}
            </a>
        @endif

        <h3 class="text-[1.05rem] font-bold leading-snug tracking-tight text-ink">
            <a href="{{ route('article.show', $article) }}" class="transition group-hover:text-accent">{{ $article->title }}</a>
        </h3>

        @if ($article->displayExcerpt())
            <p class="line-clamp-2 text-sm leading-relaxed text-muted">{{ $article->displayExcerpt() }}</p>
        @endif

        <div class="mt-auto flex items-center gap-2 pt-3 text-xs text-muted">
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('M j, Y') }}</time>
                <span aria-hidden="true">&middot;</span>
            @endif
            <span>{{ $article->displayReadingTimeMinutes() }} min read</span>
        </div>
    </div>
</article>
