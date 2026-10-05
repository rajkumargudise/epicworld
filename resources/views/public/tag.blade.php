@extends('layouts.public')

@php($context = 'tag')

@section('content')
    <h1 class="mb-8 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">#{{ $tag->name }}</h1>

    @if ($articles->isEmpty())
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No articles are tagged {{ $tag->name }} yet.</p>
    @else
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
