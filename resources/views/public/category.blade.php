@extends('layouts.public')

@section('content')
    <h1 class="mb-2 text-2xl font-bold text-slate-900">{{ $category->name }}</h1>
    @if ($category->description)
        <p class="mb-6 max-w-2xl text-slate-600">{{ $category->description }}</p>
    @else
        <div class="mb-6"></div>
    @endif

    @if ($articles->isEmpty())
        <p class="text-slate-500">No articles have been published in this category yet.</p>
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
