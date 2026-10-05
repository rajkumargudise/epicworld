{{-- Expects $article (with category and author eager-loaded). --}}
<article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-200/70">
    <a href="{{ route('article.show', $article) }}" class="block overflow-hidden" tabindex="-1" aria-hidden="true">
        @if ($article->featured_image)
            <img src="{{ $article->featured_image }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="aspect-[16/10] w-full bg-gradient-to-br from-brand-100 via-slate-100 to-slate-200"></div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-5">
        @if ($article->category)
            <a href="{{ route('category.show', $article->category) }}" class="text-xs font-semibold uppercase tracking-wider text-brand-600 hover:text-brand-700">
                {{ $article->category->name }}
            </a>
        @endif

        <h3 class="text-base font-bold leading-snug tracking-tight text-slate-900">
            <a href="{{ route('article.show', $article) }}" class="group-hover:text-brand-700">{{ $article->title }}</a>
        </h3>

        @if ($article->displayExcerpt())
            <p class="line-clamp-2 text-sm leading-relaxed text-slate-600">{{ $article->displayExcerpt() }}</p>
        @endif

        <div class="mt-auto flex items-center gap-2 pt-3 text-xs text-slate-500">
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('M j, Y') }}</time>
                <span aria-hidden="true">&middot;</span>
            @endif
            <span>{{ $article->displayReadingTimeMinutes() }} min read</span>
        </div>
    </div>
</article>
