@if ($site->needsConsent())
    <div id="consent-banner" class="fixed inset-x-3 bottom-3 z-50 hidden max-w-xl rounded-2xl border border-line-strong bg-surface p-5 shadow-2xl sm:left-auto sm:right-4" role="dialog" aria-live="polite" aria-label="Cookie choices">
        <p class="text-sm leading-relaxed text-ink-soft">
            We use essential cookies to run the site.
            @if ($site->analyticsEnabled()) With your permission we also use analytics @endif
            @if ($site->analyticsEnabled() && $site->adsEnabled()) and @endif
            @if ($site->adsEnabled()) show advertising @endif
            cookies. Choose what you're comfortable with &mdash; see our <a href="{{ route('privacy') }}" class="text-accent underline">Privacy policy</a>.
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" data-consent="granted" class="btn-primary rounded-full px-5 py-2 text-sm font-semibold">Accept all</button>
            <button type="button" data-consent="denied" class="chip rounded-full px-5 py-2 text-sm font-semibold">Essential only</button>
        </div>
    </div>
@endif
