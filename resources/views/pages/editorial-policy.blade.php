@extends('layouts.public')

@php($context = 'page')

@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Policy</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">Editorial policy</h1>
        <div class="article-body mt-8">
            <h2>Accuracy and sourcing</h2>
            <p>Our stories are built from named, published sources, which are listed at the end of each article. We do not invent quotes, figures or events. When reporting is thin we say less rather than guess, and we update stories when new facts emerge.</p>

            <h2>Live desk</h2>
            <p>The live desk collects headlines and video from established news organisations, each credited to its publisher. For many headlines EPIC World also writes a full report in its own words, based on that publisher's reporting and on what other outlets have reported about the same development. We name the outlets on every report, we do not copy their articles, and an automated check rejects any draft that reuses long passages of the original wording.</p>
            <p>Live reports are written with AI and published quickly, so they are not individually reviewed by an editor before they appear. Details can change as a story develops, and our <a href="{{ route('disclaimer') }}">disclaimer</a> says so plainly. Where a live report is wrong, tell us and we will correct or remove it.</p>

            <h2>How we use AI</h2>
            <p>EPIC World uses AI tools to help write. Guides, explainers and blog posts are drafted with AI, then read and approved by a person before they are scheduled or published, and sensitive topics (such as health) get an additional human check. Live desk reports are the exception described above. AI is never used to create fake images, fake quotes or fake sources, and we never present AI output as an eyewitness account.</p>

            <h2>Community contributions</h2>
            <p>Posts from readers are clearly labelled &ldquo;Community contributor&rdquo;. Each one is reviewed by an editor before it appears, and we may decline or edit submissions that are inaccurate, plagiarised, promotional, defamatory or otherwise break our <a href="{{ route('terms') }}">terms</a>.</p>

            <h2>Comments</h2>
            <p>Comments are moderated. A comment appears only after a moderator approves it, and we remove comments that are abusive, spam, off-topic or unlawful.</p>

            <h2>Corrections</h2>
            <p>If you believe we have made an error, please <a href="{{ route('contact') }}">contact us</a> with the page address and what needs correcting. Where a correction is warranted we update the story and note that it has been updated.</p>

            <h2>Independence and advertising</h2>
            <p>Advertising, where shown, is clearly separate from editorial content and never influences what we publish.</p>
        </div>
    </article>
@endsection
