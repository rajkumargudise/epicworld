@extends('layouts.public')

@php($context = 'category')

@section('content')
    <header class="relative mb-10 overflow-hidden rounded-3xl border border-line px-6 py-12 sm:px-10">
        <div class="hero-glow absolute inset-0" aria-hidden="true"></div>
        <div class="relative">
            <p class="text-xs font-semibold uppercase tracking-wider text-accent">Category</p>
            <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-3 max-w-2xl text-ink-soft">{{ $category->description }}</p>
            @endif

            @if ($topics->isNotEmpty())
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach ($topics as $topic)
                        <a href="{{ route('search', ['q' => $topic->name]) }}" class="chip rounded-full px-3 py-1 text-xs font-medium">{{ $topic->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </header>

    @if ($articles->isEmpty())
        <p class="rounded-2xl border border-dashed border-line-strong bg-surface p-10 text-center text-muted">No articles have been published in this category yet.</p>
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
