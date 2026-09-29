@extends('layouts.public')

@php($context = 'category')

@section('content')
    <h1 class="mb-2 text-2xl font-bold text-slate-900">{{ $category->name }}</h1>
    @if ($category->description)
        <p class="mb-4 max-w-2xl text-slate-600">{{ $category->description }}</p>
    @endif

    @if ($topics->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($topics as $topic)
                <a href="{{ route('search', ['q' => $topic->name]) }}"
                   class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-200">
                    {{ $topic->name }}
                </a>
            @endforeach
        </div>
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
