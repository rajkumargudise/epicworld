@extends('layouts.public')

@php($context = 'category')

@section('content')
    <header class="mb-8 rounded-3xl bg-gradient-to-br from-brand-600 to-slate-900 px-6 py-10 text-white sm:px-10">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $category->name }}</h1>
        @if ($category->description)
            <p class="mt-2 max-w-2xl text-brand-100">{{ $category->description }}</p>
        @endif

        @if ($topics->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($topics as $topic)
                    <a href="{{ route('search', ['q' => $topic->name]) }}"
                       class="rounded-full bg-white/15 px-3 py-1 text-xs font-medium text-white transition hover:bg-white/25">
                        {{ $topic->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    @if ($articles->isEmpty())
        <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No articles have been published in this category yet.</p>
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
