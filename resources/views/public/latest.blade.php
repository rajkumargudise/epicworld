@extends('layouts.public')

@php($context = 'latest')

@section('content')
    <h1 class="mb-8 text-4xl font-extrabold tracking-tight sm:text-5xl">Latest</h1>

    @if ($articles->isEmpty())
        <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-muted">No articles have been published yet.</p>
    @else
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
