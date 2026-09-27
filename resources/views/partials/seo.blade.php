{{--
    Shared SEO foundation for every public page. Expects: $seoTitle,
    $seoDescription, $canonicalUrl, $indexable (bool), and optionally
    $ogImage and $jsonLd (an array to be encoded as a NewsArticle/etc.
    JSON-LD block). When $indexable is false, only the title/description
    and an explicit noindex directive are emitted - no canonical link,
    Open Graph tags, or structured data, since those are themselves
    signals meant to help a page get indexed and shared.
--}}
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">

@if ($indexable)
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ config('app.name', 'EPIC World') }}">
    @if (! empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    <meta name="twitter:card" content="{{ ! empty($ogImage) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">

    @if (! empty($jsonLd))
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@else
    <meta name="robots" content="noindex, nofollow">
@endif
