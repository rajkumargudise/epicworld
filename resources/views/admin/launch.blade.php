@extends('layouts.admin')

@section('title', 'Google readiness — EPIC World Admin')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Google readiness</h1>
    <p class="mb-6 max-w-3xl text-sm text-slate-500">
        Everything within the site's control that Google Search Console, Analytics and AdSense reviewers look at.
        <strong>AdSense approval is decided by Google</strong> after you apply &mdash; this page shows whether the site is ready, not whether it is approved.
    </p>

    @foreach ($groups as $group => $checks)
        <h2 class="mb-2 mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</h2>
        <div class="space-y-2">
            @foreach ($checks as $check)
                <div class="flex gap-3 rounded-lg border bg-white p-4 {{ $check['status'] === 'pass' ? 'border-emerald-200' : ($check['status'] === 'warn' ? 'border-amber-300' : 'border-red-300') }}">
                    <span class="mt-0.5 text-lg leading-none">{{ $check['status'] === 'pass' ? '✅' : ($check['status'] === 'warn' ? '⚠️' : '❌') }}</span>
                    <div>
                        <p class="font-medium">{{ $check['title'] }}</p>
                        <p class="text-sm text-slate-600">{{ $check['detail'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <h2 class="mb-2 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Your Google files</h2>
    <ul class="space-y-1 text-sm">
        @foreach ($urls as $label => $url)
            <li><span class="font-medium">{{ $label }}:</span> <a class="text-indigo-700 underline" href="{{ $url }}" target="_blank">{{ $url }}</a></li>
        @endforeach
    </ul>

    <h2 class="mb-2 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Go-live steps</h2>
    <ol class="list-decimal space-y-2 pl-5 text-sm text-slate-700">
        <li>Point <strong>epicworld.in</strong> at this app (cutover) and confirm HTTPS works.</li>
        <li>In <a class="underline" href="https://search.google.com/search-console" target="_blank">Search Console</a> add the domain, copy the HTML-tag token into Settings, click <em>Verify</em>, then submit the sitemap.</li>
        <li>Create a <a class="underline" href="https://analytics.google.com" target="_blank">GA4</a> property and paste its measurement ID into Settings.</li>
        <li>Publish plenty of original, useful articles (and approve contributor posts), then apply at <a class="underline" href="https://adsense.google.com" target="_blank">AdSense</a> with your real domain. Paste the publisher ID into Settings so <code>ads.txt</code> and the verification tag are live when Google checks.</li>
        <li>When Google approves the site, switch <em>Show AdSense ads</em> on in Settings.</li>
    </ol>
@endsection
