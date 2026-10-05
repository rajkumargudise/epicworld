@extends('layouts.admin')

@section('title', 'Dashboard — EPIC World Admin')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Dashboard</h1>

    {{-- Needs your attention --}}
    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Needs your attention</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <a href="{{ route('admin.review.index') }}" class="rounded-lg border bg-white p-4 hover:border-slate-400 {{ $reviewArticles ? 'border-indigo-300' : 'border-slate-200' }}">
                <div class="text-2xl font-semibold">{{ $reviewArticles }}</div>
                <div class="text-sm text-slate-500">Articles to review</div>
                @if ($contributorReview)<div class="mt-1 text-xs text-indigo-700">{{ $contributorReview }} from contributors</div>@endif
            </a>
            <a href="{{ route('admin.comments.index') }}" class="rounded-lg border bg-white p-4 hover:border-slate-400 {{ $pendingComments ? 'border-indigo-300' : 'border-slate-200' }}">
                <div class="text-2xl font-semibold">{{ $pendingComments }}</div>
                <div class="text-sm text-slate-500">Comments awaiting approval</div>
            </a>
            <a href="{{ route('admin.messages.index') }}" class="rounded-lg border bg-white p-4 hover:border-slate-400 {{ $unreadMessages ? 'border-indigo-300' : 'border-slate-200' }}">
                <div class="text-2xl font-semibold">{{ $unreadMessages }}</div>
                <div class="text-sm text-slate-500">Unread messages</div>
            </a>
            <a href="{{ route('admin.launch') }}" class="rounded-lg border border-slate-200 bg-white p-4 hover:border-slate-400">
                <div class="text-2xl font-semibold">{{ $readiness['pass'] }}/{{ $readiness['total'] }}</div>
                <div class="text-sm text-slate-500">Google readiness checks</div>
            </a>
        </div>
    </div>

    {{-- Site health --}}
    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Site health</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="text-2xl font-semibold">{{ number_format($wireItems) }}</div>
                <div class="text-sm text-slate-500">Live-desk items</div>
                <div class="mt-1 text-xs text-slate-400">{{ $wireLastRun ? 'Updated '.$wireLastRun->diffForHumans() : 'Not fetched yet' }}</div>
            </div>
            <div class="rounded-lg border bg-white p-4 {{ $failingFeeds ? 'border-amber-300' : 'border-slate-200' }}">
                <div class="text-2xl font-semibold">{{ $failingFeeds }}</div>
                <div class="text-sm text-slate-500">Failing source feeds</div>
            </div>
            <div class="rounded-lg border bg-white p-4 {{ $aiReady ? 'border-slate-200' : 'border-amber-300' }}">
                <div class="text-2xl font-semibold">{{ $pendingJobs }}</div>
                <div class="text-sm text-slate-500">Stories queued for AI</div>
                <div class="mt-1 text-xs {{ $aiReady ? 'text-emerald-700' : 'text-amber-700' }}">{{ $aiReady ? 'AI writer ready' : 'No AI key configured' }}</div>
            </div>
            <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-200 bg-white p-4 hover:border-slate-400">
                <div class="text-2xl font-semibold">{{ $userCount }}</div>
                <div class="text-sm text-slate-500">Users</div>
                <div class="mt-1 text-xs text-slate-400">{{ $contributorCount }} contributors</div>
            </a>
        </div>
    </div>

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
