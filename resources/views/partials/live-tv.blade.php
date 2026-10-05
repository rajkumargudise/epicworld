{{-- Live TV tiles: 24/7 news channels, embedded on click. Expects $channels (array). --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($channels as $channel)
        <article class="card group overflow-hidden rounded-2xl">
            <div class="relative aspect-video" data-embed="https://www.youtube-nocookie.com/embed/live_stream?channel={{ $channel['channel_id'] }}&autoplay=1&rel=0">
                <div class="placeholder-art absolute inset-0"></div>
                <span class="absolute left-3 top-3 flex items-center gap-1.5 rounded-full bg-red-600 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span> Live
                </span>
                <button type="button" data-embed-play class="absolute inset-0 grid place-items-center" aria-label="Watch {{ $channel['name'] }} live">
                    <span class="grid h-14 w-14 place-items-center rounded-full text-white transition group-hover:scale-110" style="background: linear-gradient(135deg, var(--accent), var(--accent-2))">
                        <svg class="ml-0.5 h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                    </span>
                </button>
            </div>
            <div class="flex items-center justify-between gap-2 p-4">
                <h3 class="text-[0.95rem] font-bold leading-snug">{{ $channel['name'] }}</h3>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ config('newswire.scopes.'.$channel['scope'].'.label') }}</span>
            </div>
        </article>
    @endforeach
</div>
