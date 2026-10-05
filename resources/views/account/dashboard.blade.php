@extends('layouts.public')

@php($context = 'account')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-accent">Contributor</p>
                <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">My posts</h1>
            </div>
            <a href="{{ route('account.posts.create') }}" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">+ Write a post</a>
        </div>

        @include('partials.flash')

        @if ($posts->isEmpty())
            <div class="story-card rounded-3xl p-10 text-center">
                <p class="text-lg font-semibold">You haven't written anything yet.</p>
                <p class="mt-1 text-sm text-muted">Write a post and submit it &mdash; our editors review every submission before it goes live.</p>
                <a href="{{ route('write') }}" class="mt-4 inline-block text-sm font-semibold text-accent hover:underline">Read the writing guidelines &rarr;</a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($posts as $post)
                    @php($rejection = $post->editorial_metadata['rejection_reason'] ?? null)
                    <article class="story-card rounded-2xl p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-bold leading-snug">{{ $post->title }}</h2>
                                <p class="mt-1 text-xs text-muted">{{ $post->category?->name }} &middot; updated {{ $post->updated_at->diffForHumans() }}</p>
                            </div>
                            @switch($post->status->value)
                                @case('published')
                                    <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-400">Published</span>
                                    @break
                                @case('review')
                                @case('scheduled')
                                    <span class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-400">In review</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-surface-2 px-3 py-1 text-xs font-bold text-muted">{{ $rejection ? 'Needs changes' : 'Draft' }}</span>
                            @endswitch
                        </div>

                        @if ($rejection)
                            <div class="mt-3 rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">
                                <strong>Editor feedback:</strong> {{ $rejection }}
                            </div>
                        @endif

                        <div class="mt-4 flex gap-4 text-sm font-semibold">
                            @if ($post->status->value === 'published')
                                <a href="{{ route('article.show', $post) }}" class="text-accent hover:underline">View live &rarr;</a>
                            @elseif ($post->status->value === 'draft')
                                <a href="{{ route('account.posts.edit', $post) }}" class="text-accent hover:underline">Edit</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <p class="mt-8 text-sm text-muted"><a class="hover:text-accent" href="{{ route('account.password.edit') }}">Change password</a></p>
    </div>
@endsection
