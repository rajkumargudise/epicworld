@extends('layouts.public')

@php($context = 'search')

@section('content')
    <h1 class="mb-6 text-4xl font-extrabold tracking-tight sm:text-5xl">Search</h1>

    <form method="GET" action="{{ route('search') }}" class="mb-10 flex max-w-xl gap-2" role="search">
        <label for="search-q" class="sr-only">Search articles</label>
        <input id="search-q" type="search" name="q" value="{{ $query }}" placeholder="Search articles&hellip;"
               class="flex-1 rounded-full border border-line-strong bg-surface px-5 py-3 text-sm text-ink outline-none placeholder:text-muted focus:border-accent">
        <button type="submit" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Search</button>
    </form>

    @if ($articles === null)
        <p class="text-muted">Enter a search term above to find articles across EPIC World.</p>
    @elseif ($articles->isEmpty())
        <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-muted">No articles matched &ldquo;{{ $query }}&rdquo;.</p>
    @else
        <p class="mb-6 text-sm text-muted">
            {{ $articles->total() }} {{ Str::plural('result', $articles->total()) }} for &ldquo;{{ $query }}&rdquo;
        </p>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($articles as $article)
                @include('partials.article-card', ['article' => $article])
            @endforeach
        </div>

        <div class="mt-12">
            {{ $articles->links() }}
        </div>
    @endif
@endsection
