@extends('layouts.public')

@php($context = 'page')

@section('content')
    <article class="mx-auto max-w-3xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">About</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">About EPIC World</h1>
        <div class="article-body mt-8">
            <p>EPIC World is an independent news and ideas site covering world affairs, India, technology, business, science and the everyday questions behind them. We publish explainers and original reports, and we run a live desk that surfaces headlines and video from newsrooms around the world &mdash; always credited to the people who reported them.</p>

            <h2>What you will find here</h2>
            <ul>
                <li><strong>Stories &amp; explainers</strong> &mdash; clear, sourced articles written for people who want the full picture, not just the headline.</li>
                <li><strong>Live desk</strong> &mdash; World, News (India) and Local (Hyderabad &amp; Telangana) headlines, plus live news TV and the latest video reports, updated every few minutes.</li>
                <li><strong>Community posts</strong> &mdash; writing from our readers. Every contribution is reviewed by an editor before it is published.</li>
            </ul>

            <h2>How we work</h2>
            <p>We separate what we report from what we link to. Live-desk items are short headline briefs that credit and link to the original publisher. Our own stories are written from the facts in that reporting, in our own words, reviewed by a person before publication, and credited to their sources. Read the full <a href="{{ route('editorial.policy') }}">editorial policy</a>, including how we use AI tools and how to request a correction.</p>

            <h2>Get involved</h2>
            <p>Have a story or an opinion? <a href="{{ route('write') }}">Write for us</a>. Spotted a mistake or want to say hello? <a href="{{ route('contact') }}">Contact the team</a>.</p>
        </div>
    </article>
@endsection
