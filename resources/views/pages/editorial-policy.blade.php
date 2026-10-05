@extends('layouts.public')

@php($context = 'page')

@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Policy</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">Editorial policy</h1>
        <div class="article-body mt-8">
            <h2>Accuracy and sourcing</h2>
            <p>Our stories are built from named, published sources, which are listed at the end of each article. We do not invent quotes, figures or events. When reporting is thin we say less rather than guess, and we update stories when new facts emerge.</p>

            <h2>Live desk headlines</h2>
            <p>The live desk shows headlines, short summaries and video from established news organisations, each clearly credited and linked to its publisher. We do not present this material as our own reporting and we do not republish publishers' full articles.</p>

            <h2>How we use AI</h2>
            <p>Some EPIC World stories are drafted with the help of AI tools from the facts in credited reporting. AI is a drafting aid, not an author: every such story is reviewed by an editor and cannot be published without human approval, and sensitive topics get additional review. AI is never used to create fake images, fake quotes or fake sources.</p>

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
