@extends('layouts.public')

@php
    $context = 'privacy';
@endphp

{{--
    This page describes what EPIC World actually does, driven by the
    site's real settings (analytics / advertising on or off). It is NOT
    a legal compliance certification and must never be edited to claim
    one. Before relying on it for a particular jurisdiction, have it
    reviewed by a qualified professional.
--}}
@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Legal</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">Privacy</h1>
        <p class="mt-2 text-sm text-muted">Last updated {{ now()->format('j F Y') }}</p>

        <div class="article-body mt-8">
            <p>
                <strong>This page is a plain description of what EPIC World does with data, not a legal
                certification.</strong> We collect as little as we can and explain it below.
            </p>

            <h2>Information we collect</h2>
            <ul>
                <li><strong>Reading the site:</strong> our servers keep standard technical logs (such as IP address, browser and pages requested) for security and to keep the site running.</li>
                <li><strong>Accounts:</strong> if you create a contributor account we store your name, email address and a securely hashed password, and the posts you submit.</li>
                <li><strong>Comments:</strong> if you comment we store your name, email address (never shown publicly), the comment text and a one-way hash of your IP address for spam control. Comments are public only after a moderator approves them.</li>
                <li><strong>Contact form:</strong> messages you send us, with your name and email address, so we can reply.</li>
            </ul>

            <h2>Cookies and similar technologies</h2>
            <p>EPIC World always uses a small number of cookies that the site needs in order to work:</p>
            <ul>
                <li>A session cookie that keeps you signed in if you have an account.</li>
                <li>A CSRF token that protects forms from being submitted from another site.</li>
                <li>A note in your browser's local storage of your theme (dark or light) and your cookie choice.</li>
            </ul>

            @if ($site->analyticsEnabled())
                <h2>Analytics</h2>
                <p>
                    If you accept analytics cookies, we use Google Analytics to understand which pages are read and how people
                    find the site. Google Analytics sets cookies and receives your IP address and usage data. We do not load it
                    unless you accept. You can change your mind at any time with the &ldquo;Cookie settings&rdquo; link in the footer.
                    Learn more in <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">how Google uses data from sites that use its services</a>.
                </p>
            @endif

            @if ($site->adsEnabled())
                <h2>Advertising</h2>
                <p>
                    EPIC World shows advertising served by Google AdSense on our own articles and listing pages. Google and its
                    partners may use cookies and similar technologies to show ads based on your visits to this and other websites,
                    and to measure ads. We load advertising scripts only if you accept advertising cookies; if you decline, no
                    advertising script runs on your visit.
                </p>
                <ul>
                    <li>You can manage ad personalisation at <a href="https://adssettings.google.com" target="_blank" rel="noopener">Google Ad Settings</a>.</li>
                    <li>Learn how Google uses information from sites that use its services at <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">policies.google.com</a>.</li>
                    <li>You can change your choice with the &ldquo;Cookie settings&rdquo; link in the footer.</li>
                </ul>
                <p>We do not show ads on our Live desk pages, account pages, login pages or this page.</p>
            @else
                <h2>Advertising is not active</h2>
                <p>
                    The site has a monetization system that is <strong>disabled by default</strong> and stays disabled until it is
                    explicitly turned on. While it is off, no advertising script loads, no advertising cookie is set and no request is
                    made to any advertising provider. If advertising is switched on, this page will be updated first to name the
                    provider and explain what it does.
                </p>
            @endif

            <h2>Third-party content</h2>
            <p>
                The Live desk can show video from YouTube (loaded only when you press play, using YouTube's privacy-enhanced
                embed) and links to other publishers. Those services have their own privacy policies.
            </p>

            <h2>How we use information</h2>
            <p>To run and secure the site, review and publish contributions, moderate comments, reply to messages and, only with your consent, measure traffic and show advertising. We do not sell your personal information.</p>

            <h2>How long we keep it</h2>
            <p>Account details are kept while your account exists. Comments and published posts stay until removed. Contact messages are kept as long as needed to deal with them. Server logs are kept for a short period.</p>

            <h2>Your choices and rights</h2>
            <p>You can ask us to access, correct or delete the personal information we hold about you, or to delete your account, by using our <a href="{{ route('contact') }}">contact form</a>. You can clear cookies in your browser at any time.</p>

            <h2>Children</h2>
            <p>EPIC World is not directed at children under 13 and we do not knowingly collect their personal information.</p>

            <h2>Changes</h2>
            <p>If we change how we use data, we will update this page and its date.</p>

            <p class="rounded-2xl border border-line bg-surface p-4 text-sm text-muted">
                This page describes our practices; it is not legal advice or a statement of compliance with any specific law.
            </p>
        </div>
    </article>
@endsection
