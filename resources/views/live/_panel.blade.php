{{-- A home-page tab panel: a lead headline with image + a list. Expects $items. --}}
@php
    $lead = $items->first(fn ($i) => $i->image_url) ?? $items->first();
    $rest = $items->reject(fn ($i) => $lead && $i->id === $lead->id)->values();
@endphp
@if ($lead)
    <div class="grid gap-5 lg:grid-cols-12">
        <article class="card group relative isolate flex min-h-[22rem] overflow-hidden rounded-3xl lg:col-span-7">
            @if ($lead->image_url)
                <img src="{{ $lead->image_url }}" alt="" referrerpolicy="no-referrer" class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-700 group-hover:scale-105">
            @else
                <div class="placeholder-art absolute inset-0 -z-10"></div>
            @endif
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/45 to-transparent"></div>
            <div class="mt-auto flex flex-col gap-3 p-6 sm:p-8">
                <div class="flex items-center gap-2 text-xs text-white/75">
                    <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white backdrop-blur">{{ $lead->source }}</span>
                    <time datetime="{{ $lead->published_at->toIso8601String() }}">{{ $lead->published_at->diffForHumans() }}</time>
                </div>
                <h3 class="text-2xl font-extrabold leading-tight tracking-tight text-white sm:text-3xl">
                    <a href="{{ $lead->path() }}" class="after:absolute after:inset-0">{{ $lead->title }}</a>
                </h3>
                @if ($lead->summary)
                    <p class="line-clamp-2 max-w-2xl text-sm leading-relaxed text-white/80">{{ $lead->summary }}</p>
                @endif
            </div>
        </article>

        <div class="card rounded-3xl p-3 lg:col-span-5">
            <div class="divide-y divide-line">
                @foreach ($rest->take(6) as $item)
                    @include('live._item', ['item' => $item])
                @endforeach
            </div>
        </div>
    </div>
@else
    <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-8 text-center text-sm text-muted">Fetching the latest headlines&hellip; check back in a moment.</p>
@endif
