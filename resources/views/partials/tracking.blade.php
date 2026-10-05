{{--
    Google integrations, in <head>. Verification meta tags are harmless and
    always emitted when configured. Anything that sets cookies or contacts
    Google for tracking/ads (GA4, AdSense) is NOT emitted here: it is loaded
    by resources/js/app.js only after the visitor accepts, and AdSense only on
    content pages (never on Live desk, account, login, privacy or error pages).
--}}
@if ($token = $site->searchConsoleToken())
    <meta name="google-site-verification" content="{{ $token }}">
@endif
@if ($publisher = $site->adsensePublisherId())
    <meta name="google-adsense-account" content="{{ $publisher }}">
@endif
@if ($site->needsConsent())
    <meta name="epic-consent" content="1">
    @if ($ga = $site->ga4Id())
        <meta name="epic-ga4" content="{{ $ga }}">
    @endif
    @if ($site->adsEnabled() && in_array($context ?? '', ['home', 'article', 'category', 'latest', 'tag'], true))
        <meta name="epic-adsense" content="{{ $site->adsensePublisherId() }}">
    @endif
@endif
