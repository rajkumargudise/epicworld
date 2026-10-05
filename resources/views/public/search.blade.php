@extends('layouts.public')

@php($context = 'search')

@section('content')
    <h1 class="mb-6 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Search</h1>

    <form method="GET" action="{{ route('search') }}" class="mb-10 flex max-w-xl gap-2" role="search">
        <label for="search-q" class="sr-only">Search articles</label>
        <input id="search-q" type="search" name="q" value="{{ $query }}" placeholder="Search articles&hellip;"
               class="flex-1 rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm outline-none focus:border-brand-500">
        <button type="submit" class="rounded-full bg-slate-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
            Search
        </button>
    </form>

    @if ($articles === null)
        <p class="text-slate-500">Enter a search term above to find articles across EPIC World.</p>
    @elseif ($articles->isEmpty())
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No articles matched &ldquo;{{ $query }}&rdquo;.</p>
    @else
        <p class="mb-6 text-sm text-slate-500">
            {{ $articles->total() }} {{ Str::plural('result', $articles->total()) }} for &ldquo;{{ $query }}&rdquo;
        </p>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($articles as $article)
                @include('partials.article-card', ['article' => $article])
            @endforeach
        </div>

        <div class="mt-10">
            {{ $articles->links() }}
        </div>
    @endif
@endsection
