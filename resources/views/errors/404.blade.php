@extends('layouts.public')

@php
    $context = 'error';
    $seoTitle = 'Page not found';
    $seoDescription = 'The page you were looking for could not be found.';
    $canonicalUrl = url('/');
    $indexable = false;
@endphp

@section('content')
    <section class="mx-auto max-w-xl py-16 text-center">
        <p class="gradient-text text-7xl font-extrabold tracking-tight">404</p>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight">We couldn't find that page</h1>
        <p class="mt-3 text-ink-soft">It may have moved or no longer exists. Try a search, or head to one of our sections.</p>

        <form method="GET" action="{{ route('search') }}" class="mx-auto mt-8 flex max-w-md gap-2" role="search">
            <label for="nf-q" class="sr-only">Search</label>
            <input id="nf-q" type="search" name="q" placeholder="Search EPIC World&hellip;"
                   class="flex-1 rounded-full border border-line-strong bg-surface px-5 py-3 text-sm text-ink outline-none placeholder:text-muted focus:border-accent">
            <button type="submit" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Search</button>
        </form>

        <div class="mt-8 flex flex-wrap justify-center gap-2">
            <a href="{{ route('home') }}" class="chip rounded-full px-4 py-2 text-sm font-medium">Home</a>
            <a href="{{ route('live') }}" class="chip rounded-full px-4 py-2 text-sm font-medium">Live news</a>
            <a href="{{ route('latest') }}" class="chip rounded-full px-4 py-2 text-sm font-medium">Latest stories</a>
            <a href="{{ route('live.scope', 'videos') }}" class="chip rounded-full px-4 py-2 text-sm font-medium">Videos</a>
        </div>
    </section>
@endsection
