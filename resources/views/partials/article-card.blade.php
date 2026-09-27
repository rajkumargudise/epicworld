{{-- Expects $article (with category and author eager-loaded). --}}
<article class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white">
    <a href="{{ route('article.show', $article) }}" class="block">
        @if ($article->featured_image)
            <img src="{{ $article->featured_image }}" alt="{{ $article->title }}" class="h-44 w-full object-cover">
        @else
            <div class="h-44 w-full bg-slate-100"></div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($article->category)
            <a href="{{ route('category.show', $article->category) }}"
               class="text-xs font-semibold uppercase tracking-wide text-indigo-600 hover:text-indigo-800">
                {{ $article->category->name }}
            </a>
        @endif

        <h3 class="text-base font-semibold leading-snug text-slate-900">
            <a href="{{ route('article.show', $article) }}" class="hover:underline">{{ $article->title }}</a>
        </h3>

        @if ($article->displayExcerpt())
            <p class="line-clamp-2 text-sm text-slate-600">{{ $article->displayExcerpt() }}</p>
        @endif

        <div class="mt-auto flex items-center gap-2 pt-2 text-xs text-slate-500">
            @if ($article->author)
                <span>{{ $article->author->name }}</span>
                <span aria-hidden="true">&middot;</span>
            @endif
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">
                    {{ $article->published_at->diffForHumans() }}
                </time>
            @endif
            <span aria-hidden="true">&middot;</span>
            <span>{{ $article->displayReadingTimeMinutes() }} min read</span>
        </div>
    </div>
</article>
