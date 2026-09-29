@extends('layouts.public')

@php($context = 'latest')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">Latest</h1>

    @if ($articles->isEmpty())
        <p class="text-slate-500">No articles have been published yet.</p>
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
