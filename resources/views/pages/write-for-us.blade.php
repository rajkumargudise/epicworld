@extends('layouts.public')

@php($context = 'page')

@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Contribute</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">Write for EPIC World</h1>
        <p class="mt-4 text-lg text-ink-soft">Have something worth saying? Create a free account, write your post, and submit it. Our editors read every submission before anything is published.</p>

        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('account.posts.create') }}" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Write a post</a>
            @else
                <a href="{{ route('register') }}" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Create an account</a>
                <a href="{{ route('login') }}" class="chip rounded-full px-6 py-3 text-sm font-semibold">Log in</a>
            @endauth
        </div>

        <div class="article-body mt-10">
            <h2>What we are looking for</h2>
            <ul>
                <li>Original writing &mdash; explainers, analysis, how-tos and first-hand experience.</li>
                <li>Topics across technology, AI, business, startups, careers, science, finance, education, India and the world.</li>
                <li>At least about 100 words; most strong posts are 500&ndash;1,500.</li>
                <li>A clear title, a one-line summary, and sections with short headings.</li>
            </ul>

            <h2>What we will not publish</h2>
            <ul>
                <li>Anything copied from elsewhere, including AI-generated text you have not checked and made your own.</li>
                <li>Promotional posts, link-farming, or posts whose main purpose is to sell something.</li>
                <li>Hate, harassment, defamation, misinformation or unlawful content.</li>
            </ul>

            <h2>How review works</h2>
            <p>After you submit, your post is &ldquo;in review&rdquo;. An editor will publish it, or send it back with feedback so you can improve it and resubmit. Published posts are labelled &ldquo;Community contributor&rdquo; and credited to you. You can see the status of every post in your account.</p>

            <h2>Formatting</h2>
            <p>Posts are plain text. Start a line with <code>## </code> for a section heading, <code>- </code> for bullet points and <code>&gt; </code> for a quote; leave a blank line between paragraphs. See the <a href="{{ route('terms') }}">terms</a> for the rules every contributor agrees to.</p>
        </div>
    </article>
@endsection
