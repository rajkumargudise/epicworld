@extends('layouts.public')

@php($context = 'page')

@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Policy</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">Disclaimer</h1>
        <div class="article-body mt-8">
            <h2>General information only</h2>
            <p>Everything on EPIC World is published for general information and education. It is not legal, medical, financial, tax or other professional advice, and it should not be relied on as a substitute for advice from a qualified professional who knows your circumstances.</p>

            <h2>Health content</h2>
            <p>Our health guides explain topics in general terms. They do not diagnose, treat or prevent any condition. If you have symptoms, a medical condition or questions about medicines, speak to a registered doctor. In an emergency, contact your local emergency services immediately.</p>

            <h2>Money and investing</h2>
            <p>Articles about investing, mutual funds, payments or other financial topics are educational. We are not a registered investment adviser. Investments carry risk, past performance does not guarantee future results, and you should check products and costs yourself or with a licensed adviser before you decide.</p>

            <h2>Live news reports</h2>
            <p>Reports on the live desk are written by EPIC World with the help of AI, in our own words, from published reporting by the news organisations credited on each page. They are published quickly and may not be individually reviewed by an editor, so details can change as stories develop. For the definitive account of an event, please follow the credited original reporting and official announcements.</p>

            <h2>External links and sources</h2>
            <p>We link to and credit third-party sources, videos and websites. We do not control their content and are not responsible for it. Live TV and video players are provided by YouTube and are subject to its terms.</p>

            <h2>Accuracy and corrections</h2>
            <p>We work to keep information accurate and current, but we cannot guarantee it is complete or error-free. If you spot a mistake, please <a href="{{ route('contact') }}">tell us</a> and we will review and correct it.</p>

            <h2>Advertising</h2>
            <p>Where advertising is shown it is clearly separate from editorial content and does not influence what we publish. Our <a href="{{ route('editorial.policy') }}">editorial policy</a> explains how we source, write and review our work.</p>
        </div>
    </article>
@endsection
