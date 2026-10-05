@extends('layouts.public')

@php
    $context = 'privacy';
@endphp

{{--
    Milestone 18: describes what this application actually does today.
    This is NOT a legal compliance certification and must never be
    edited to claim one (see the notice below, and AdSlotRegistry /
    config/monetization.php's own docblocks for the same boundary on
    the code side). Before any real advertising or third-party
    tracking is activated, this page's content - and the consent
    mechanism it describes - needs an actual legal review for whatever
    jurisdictions the site serves; nothing here substitutes for that.
--}}
@section('content')
    <article class="mx-auto max-w-2xl">
        <h1 class="text-4xl font-extrabold tracking-tight text-ink">Privacy</h1>

        <div class="article-body mt-8">
            <p>
                <strong>This page is a plain description of what EPIC World does today, not a legal
                certification.</strong> The wording below will be finalized, and reviewed for the
                jurisdictions this site serves, before any advertising is switched on.
            </p>

            <h2>What this site does today</h2>
            <p>
                EPIC World does not currently run any advertising, analytics, or third-party
                tracking scripts. The only cookies it sets are the ones needed for the site to
                work at all:
            </p>
            <ul>
                <li>A session cookie, used to keep you signed in if you have an editorial account.</li>
                <li>A CSRF token, used to protect forms from being submitted from another site.</li>
            </ul>
            <p>
                Neither cookie is used for advertising, profiling, or tracking you across other
                websites, and no data collected through them is sold or shared with third parties.
            </p>

            <h2>Advertising is not active</h2>
            <p>
                The site's codebase has a monetization system that is
                <strong>disabled by default</strong> and stays disabled until it is explicitly
                turned on with a configured advertising provider. While it is off, as it is now,
                no advertising script loads, no advertising cookie is set, and no request is made
                to any advertising provider - on any page, including this one.
            </p>

            <h2>When advertising is turned on in the future</h2>
            <p>
                If EPIC World enables advertising, that will mean a third-party provider (such as
                Google AdSense) may set its own cookies and load its own scripts to show ads,
                which can include personalized advertising based on your activity. Before that
                happens:
            </p>
            <ul>
                <li>This page will be rewritten to name the actual provider(s) in use and describe what they do.</li>
                <li>A real consent mechanism appropriate to the visitor's jurisdiction will be put in place before any advertising cookie is set for that visitor.</li>
                <li>The applicable privacy and consent requirements (for example under GDPR, India's DPDP Act, or CCPA) will be reviewed and accounted for - not assumed or hardcoded ahead of time.</li>
            </ul>

            <h2>Editorial content and sources</h2>
            <p>
                Articles on this site may be drafted with AI assistance from sourced reporting,
                and are reviewed by a human editor before publication. Source articles are linked
                where shown.
            </p>

            <h2>Contact</h2>
            <p>
                Questions about this page can be sent to the site's editorial team.
            </p>
        </div>
    </article>
@endsection
