{{-- Big image-led story card. Expects $article; optional $tall (bool). --}}
<article class="card group relative isolate flex overflow-hidden rounded-3xl {{ ($tall ?? false) ? 'min-h-[26rem] lg:min-h-full' : 'min-h-[18rem]' }}">
    @if ($article->featured_image)
        <img src="{{ $article->featured_image }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-700 group-hover:scale-105">
    @else
        <div class="placeholder-art absolute inset-0 -z-10"></div>
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/45 to-transparent"></div>

    <div class="mt-auto flex flex-col gap-3 p-6 sm:p-8">
        <div class="flex items-center gap-2">
            @if ($article->category)
                <a href="{{ route('category.show', $article->category) }}" class="rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white backdrop-blur hover:bg-white/25">{{ $article->category->name }}</a>
            @endif
            @if ($article->published_at)
                <time class="text-xs text-white/70" datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->diffForHumans() }}</time>
            @endif
        </div>
        <h2 class="{{ ($tall ?? false) ? 'text-3xl sm:text-4xl' : 'text-xl sm:text-2xl' }} font-extrabold leading-tight tracking-tight text-white">
            <a href="{{ route('article.show', $article) }}" class="after:absolute after:inset-0">{{ $article->title }}</a>
        </h2>
        @if (($tall ?? false) && $article->displayExcerpt())
            <p class="line-clamp-2 max-w-2xl text-sm leading-relaxed text-white/80 sm:text-base">{{ $article->displayExcerpt() }}</p>
        @endif
    </div>
</article>
