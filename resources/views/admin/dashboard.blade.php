@extends('layouts.admin')

@section('title', 'Dashboard — EPIC World Admin')

@php
    $hour = (int) now('Asia/Kolkata')->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $statusBadge = [
        'published' => ['badge-ok', 'Published'], 'scheduled' => ['badge-info', 'Scheduled'],
        'review' => ['badge-warn', 'In review'], 'draft' => ['badge-muted', 'Draft'], 'archived' => ['badge-muted', 'Archived'],
    ];
    $todo = $reviewArticles + $pendingComments + $unreadMessages;
@endphp

@section('content')
    {{-- Hero --}}
    <section class="admin-card hero-glow relative mb-8 overflow-hidden p-6 sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-muted">{{ now('Asia/Kolkata')->format('l, j F Y') }}</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $greeting }}, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h2>
                <p class="mt-2 max-w-xl text-sm text-ink-soft">
                    @if ($todo > 0)
                        You have <strong class="text-ink">{{ $todo }}</strong> {{ \Illuminate\Support\Str::plural('item', $todo) }} waiting for you.
                    @else
                        You're all caught up. Nothing is waiting for review.
                    @endif
                    @if ($upcoming->isNotEmpty())
                        Next post goes live <strong class="text-ink">{{ $upcoming->first()->published_at->timezone('Asia/Kolkata')->format('D j M, g:i A') }} IST</strong>.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.blog-writer.create') }}" class="btn-primary rounded-lg px-4 py-2.5 text-sm font-semibold">Write a blog</a>
                <a href="{{ route('admin.review.index') }}" class="rounded-lg border border-line-strong bg-surface px-4 py-2.5 text-sm font-medium text-ink hover:border-accent">Review queue</a>
                <a href="{{ route('admin.launch') }}" class="rounded-lg border border-line-strong bg-surface px-4 py-2.5 text-sm font-medium text-ink hover:border-accent">Google &amp; SEO</a>
            </div>
        </div>
    </section>

    {{-- Key numbers --}}
    <section class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="admin-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-muted">Live articles</p>
            <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($liveCount) }}</p>
            <p class="mt-1 text-xs text-muted">{{ $published7 }} in the last 7 days</p>
        </div>
        <div class="admin-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-muted">Scheduled</p>
            <p class="mt-2 text-3xl font-bold text-ink">{{ $upcoming->count() }}</p>
            <p class="mt-1 text-xs text-muted">{{ $upcoming->isNotEmpty() ? 'through '.$upcoming->last()->published_at->timezone('Asia/Kolkata')->format('j M') : 'nothing queued' }}</p>
        </div>
        <div class="admin-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-muted">Live desk items</p>
            <p class="mt-2 text-3xl font-bold text-ink">{{ number_format($wireItems) }}</p>
            <p class="mt-1 text-xs text-muted">{{ $wireLastRun ? 'Updated '.$wireLastRun->diffForHumans() : 'Not fetched yet' }}</p>
        </div>
        <a href="{{ route('admin.launch') }}" class="admin-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-muted">Google readiness checks</p>
            <p class="mt-2 text-3xl font-bold text-ink">{{ $readiness['pass'] }}<span class="text-lg font-medium text-muted">/{{ $readiness['total'] }}</span></p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" style="width:{{ $readiness['total'] ? round($readiness['pass'] / $readiness['total'] * 100) : 0 }}%;background:linear-gradient(90deg,var(--accent),var(--accent-2))"></div></div>
        </a>
    </section>

    <div class="mb-8 grid gap-6 lg:grid-cols-5">
        {{-- Needs attention --}}
        <section class="lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">Needs your attention</h3>
            <div class="space-y-3">
                <a href="{{ route('admin.review.index') }}" class="admin-stat flex items-center justify-between {{ $reviewArticles ? 'is-hot' : '' }}">
                    <div><p class="font-medium text-ink">Articles to review</p>@if ($contributorReview)<p class="text-xs text-muted">{{ $contributorReview }} from contributors</p>@endif</div>
                    <span class="text-2xl font-bold text-ink">{{ $reviewArticles }}</span>
                </a>
                <a href="{{ route('admin.comments.index') }}" class="admin-stat flex items-center justify-between {{ $pendingComments ? 'is-hot' : '' }}">
                    <p class="font-medium text-ink">Comments awaiting approval</p>
                    <span class="text-2xl font-bold text-ink">{{ $pendingComments }}</span>
                </a>
                <a href="{{ route('admin.messages.index') }}" class="admin-stat flex items-center justify-between {{ $unreadMessages ? 'is-hot' : '' }}">
                    <p class="font-medium text-ink">Unread messages</p>
                    <span class="text-2xl font-bold text-ink">{{ $unreadMessages }}</span>
                </a>
                @if ($sensitivePendingReview > 0)
                    <div class="admin-stat is-warn text-sm text-ink-soft">
                        <strong class="text-ink">{{ $sensitivePendingReview }}</strong> sensitive {{ \Illuminate\Support\Str::plural('article', $sensitivePendingReview) }} need human review before approval.
                    </div>
                @endif
            </div>
        </section>

        {{-- Publishing calendar --}}
        <section class="lg:col-span-3">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">Publishing schedule</h3>
            <div class="admin-card divide-y divide-line">
                @forelse ($upcoming as $a)
                    <a href="{{ route('admin.articles.edit', $a) }}" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-surface-2">
                        <div class="w-14 shrink-0 rounded-lg bg-surface-2 py-1.5 text-center">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted">{{ $a->published_at->timezone('Asia/Kolkata')->format('M') }}</p>
                            <p class="text-lg font-bold leading-none text-ink">{{ $a->published_at->timezone('Asia/Kolkata')->format('j') }}</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ $a->title }}</p>
                            <p class="text-xs text-muted">{{ $a->published_at->timezone('Asia/Kolkata')->format('l, g:i A') }} IST</p>
                        </div>
                        <span class="badge badge-info">Queued</span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-muted">No posts are scheduled. Approve a draft in the review queue to line one up.</p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Pipeline --}}
    <section class="mb-8">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">Content pipeline</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ([['Draft', $draftArticles], ['In review', $reviewArticles], ['Scheduled', $scheduledArticles], ['Published', $publishedArticles], ['Story candidates', $candidateStories], ['Stories processing', $processingStories]] as [$label, $n])
                <div class="admin-stat !p-4">
                    <p class="text-2xl font-bold text-ink">{{ number_format($n) }}</p>
                    <p class="text-xs text-muted">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mb-8 grid gap-6 lg:grid-cols-5">
        {{-- Recent activity --}}
        <section class="lg:col-span-3">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">Recently updated</h3>
            <div class="admin-card divide-y divide-line">
                @foreach ($recent as $a)
                    @php [$cls, $lbl] = $statusBadge[$a->status->value] ?? ['badge-muted', $a->status->value]; @endphp
                    <a href="{{ route('admin.articles.edit', $a) }}" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-surface-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ $a->title }}</p>
                            <p class="text-xs text-muted">{{ $a->category?->name ?? 'Uncategorised' }} &middot; {{ $a->updated_at->diffForHumans() }}</p>
                        </div>
                        <span class="badge {{ $cls }}">{{ $lbl }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- System health --}}
        <section class="lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">Site health</h3>
            <div class="admin-card divide-y divide-line text-sm">
                <div class="flex items-center justify-between px-5 py-3.5"><span class="text-ink-soft">AI writer</span><span class="badge {{ $aiReady ? 'badge-ok' : 'badge-warn' }}">{{ $aiReady ? 'Ready' : 'No key configured' }}</span></div>
                <div class="flex items-center justify-between px-5 py-3.5"><span class="text-ink-soft">News feeds</span><span class="badge {{ $failingFeeds ? 'badge-warn' : 'badge-ok' }}">{{ $failingFeeds ? $failingFeeds.' failing' : 'All healthy' }}</span></div>
                <div class="flex items-center justify-between px-5 py-3.5"><span class="text-ink-soft">Live wire</span><span class="badge {{ $wireLastRun && $wireLastRun->gt(now()->subMinutes(5)) ? 'badge-ok' : 'badge-warn' }}">{{ $wireLastRun ? $wireLastRun->diffForHumans() : 'Never' }}</span></div>
                <div class="flex items-center justify-between px-5 py-3.5"><span class="text-ink-soft">Stories queued for AI</span><span class="font-semibold text-ink">{{ $pendingJobs }}</span></div>
                <a href="{{ route('admin.users.index') }}" class="flex items-center justify-between px-5 py-3.5 transition hover:bg-surface-2"><span class="text-ink-soft">Users</span><span class="font-semibold text-ink">{{ $userCount }} <span class="font-normal text-muted">({{ $contributorCount }} contributors)</span></span></a>
                <div class="flex items-center justify-between px-5 py-3.5"><span class="text-ink-soft">Comments (all)</span><span class="font-semibold text-ink">{{ $commentsTotal }}</span></div>
            </div>
        </section>
    </div>
@endsection
