{{--
    Shared SEO foundation for every public page.

    $seoTitle, $seoDescription - always required.
    $canonicalUrl - the URL this page should canonicalize to.
    $indexable (bool) - controls the default robots directive and
        whether Open Graph / Twitter card / JSON-LD are emitted. Those
        tags are themselves signals meant to help a page get indexed
        and shared, so a genuinely non-indexable page (search,
        allow_indexing = false, ...) gets none of them.
    $showCanonical (bool, optional, default: $indexable) - whether to
        emit the canonical link at all. This is independent of
        $indexable so a paginated page beyond page one can still carry
        a self-referencing canonical while being noindexed (see the
        pagination policy below) - unlike search, which has no
        canonical-content identity of its own and must never emit one.
    $robotsContent (string, optional) - overrides the robots meta
        content that would otherwise be derived from $indexable. Used
        for the deliberate "noindex, follow" pagination policy: a page
        2+ shouldn't be indexed as its own destination, but its
        outbound links should still be crawled - a different policy
        than the blanket "noindex, nofollow" applied to genuinely
        non-indexable content like search results.
    $ogImage, $ogType, $jsonLd - optional, only meaningful when
        $indexable is true.
--}}
@php
    $showCanonical = $showCanonical ?? $indexable;
    $robotsContent = $robotsContent ?? ($indexable ? 'index, follow' : 'noindex, nofollow');
@endphp
@php
    $siteName = config('app.name', 'EPIC World');
    // "Page title | EPIC World" when it fits in a search result (~60 chars); category pages already carry the name.
    $fullTitle = (str_contains($seoTitle, $siteName) || mb_strlen($seoTitle) > 48) ? $seoTitle : $seoTitle.' | '.$siteName;
    $shareImage = ! empty($ogImage) ? $ogImage : url('/brand/og-default.jpg');
    $shareImageIsDefault = empty($ogImage);
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $seoDescription), 158, '…') }}">
<meta name="robots" content="{{ $robotsContent }}">

@if ($showCanonical && ! empty($canonicalUrl))
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endif

@if ($indexable)
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:locale" content="en_IN">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ config('app.name', 'EPIC World') }}">
    <meta property="og:image" content="{{ $shareImage }}">
    @if ($shareImageIsDefault)
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="EPIC World">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $shareImage }}">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">

    @if (! empty($jsonLd))
        {{--
            JSON_HEX_TAG/AMP/APOS/QUOT escape <, >, &, ', " as \uXXXX
            sequences - the standard safe way to embed JSON inside an
            HTML script context. Without this, an article title or
            excerpt containing the literal string "</script>" could
            close the tag early and inject markup; these flags make
            that impossible regardless of what real content contains.
        --}}
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
    @if (! empty($breadcrumbJsonLd))
        <script type="application/ld+json">{!! json_encode($breadcrumbJsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
@endif
