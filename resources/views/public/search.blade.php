@extends('layouts.public')

@php($context = 'search')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Search</h1>

    <form method="GET" action="{{ route('search') }}" class="mb-8 flex max-w-xl gap-2" role="search">
        <label for="search-q" class="sr-only">Search articles</label>
        <input id="search-q" type="search" name="q" value="{{ $query }}" placeholder="Search articles&hellip;"
               class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Search
        </button>
    </form>

    @if ($articles === null)
        <p class="text-slate-500">Enter a search term above to find articles across EPIC World.</p>
    @elseif ($articles->isEmpty())
        <p class="text-slate-500">No articles matched &ldquo;{{ $query }}&rdquo;.</p>
    @else
        <p class="mb-6 text-sm text-slate-500">
            {{ $articles->total() }} {{ Str::plural('result', $articles->total()) }} for &ldquo;{{ $query }}&rdquo;
        </p>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($articles as $article)
                @include('partials.article-card', ['article' => $article])
            @endforeach
        </div>

        <div class="mt-8">
            {{ $articles->links() }}
        </div>
    @endif
@endsection
