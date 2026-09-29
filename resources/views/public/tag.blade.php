@extends('layouts.public')

@php($context = 'tag')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">#{{ $tag->name }}</h1>

    @if ($articles->isEmpty())
        <p class="text-slate-500">No articles are tagged {{ $tag->name }} yet.</p>
    @else
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
