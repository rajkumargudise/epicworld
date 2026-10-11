{{-- The full EpicWorld report for a live-desk headline. Expects $item and $layout (StoryRenderer output). --}}
@php
    $outlets = collect($item->report_meta['outlets'] ?? [$item->source])->filter()->values();
@endphp

@if (count($layout['takeaways']))
    <section class="takeaways mt-8 rounded-3xl p-6 sm:p-8" aria-labelledby="takeaways-h">
        <h2 id="takeaways-h" class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-accent">
            <span class="grid h-6 w-6 place-items-center rounded-full bg-accent/15">&#9889;</span> Key takeaways
        </h2>
        <ul class="space-y-3">
            @foreach ($layout['takeaways'] as $point)
                <li class="flex gap-3 text-[1.02rem] leading-relaxed text-ink">
                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                    <span>{!! $point !!}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if ($layout['lead'])
    <p class="mt-8 text-xl leading-relaxed text-ink sm:text-[1.3rem] sm:leading-[1.65]">{!! $layout['lead'] !!}</p>
@endif

<div class="mt-8 space-y-6">
    @foreach ($layout['sections'] as $section)
        <section id="{{ $section['id'] }}" class="story-card section-{{ $section['variant'] }} scroll-mt-24 rounded-3xl p-6 sm:p-9">
            @if ($section['title'] !== '')
                <h2 class="mb-5 text-2xl font-extrabold leading-tight tracking-tight sm:text-3xl">{{ $section['title'] }}</h2>
            @endif

            <div class="story-prose space-y-5">
                @foreach ($section['blocks'] as $block)
                    @switch($block['type'])
                        @case('p')
                            <p>{!! $block['html'] !!}</p>
                            @break
                        @case('quote')
                            <blockquote class="pullquote">{!! $block['html'] !!}</blockquote>
                            @break
                        @case('ul')
                            <ul class="checklist">
                                @foreach ($block['items'] as $point)
                                    <li>{!! $point !!}</li>
                                @endforeach
                            </ul>
                            @break
                        @case('ol')
                            <ol class="numbered">
                                @foreach ($block['items'] as $point)
                                    <li>{!! $point !!}</li>
                                @endforeach
                            </ol>
                            @break
                    @endswitch
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<div class="story-card mt-6 rounded-3xl p-6">
    <p class="text-xs font-bold uppercase tracking-wider text-muted">Sources &amp; credit</p>
    <p class="mt-2 text-sm leading-relaxed text-ink-soft">
        Written by EpicWorld in its own words from published reporting by {{ \Illuminate\Support\Arr::join($outlets->all(), ', ', ' and ') }}.
        Original reporting belongs to its publishers.
        <a href="{{ $item->url }}" target="_blank" rel="nofollow noopener" class="whitespace-nowrap font-semibold text-accent hover:underline">{{ $item->source }}&rsquo;s original &#8599;</a>
    </p>
</div>
