@extends('layouts.admin')

@section('title', 'Dashboard — EPIC World Admin')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Dashboard</h1>

    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Stories</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $candidateStories }}</div>
                <div class="text-sm text-slate-500">Candidate</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $processingStories }}</div>
                <div class="text-sm text-slate-500">Processing</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $reviewStories }}</div>
                <div class="text-sm text-slate-500">In Review</div>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Articles</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $draftArticles }}</div>
                <div class="text-sm text-slate-500">Draft</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $reviewArticles }}</div>
                <div class="text-sm text-slate-500">In Review</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $scheduledArticles }}</div>
                <div class="text-sm text-slate-500">Scheduled</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ $publishedArticles }}</div>
                <div class="text-sm text-slate-500">Published</div>
            </div>
        </div>
    </div>

    @if ($sensitivePendingReview > 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>{{ $sensitivePendingReview }}</strong>
            {{ Str::plural('article', $sensitivePendingReview) }} flagged as sensitive
            {{ $sensitivePendingReview === 1 ? 'is' : 'are' }} awaiting human review before it can be approved or published.
        </div>
    @endif

    <div class="mt-8">
        <a href="{{ route('admin.stories.index') }}" class="text-sm font-medium text-slate-900 underline">
            View the story queue &rarr;
        </a>
    </div>
@endsection
