@extends('layouts.admin')

@section('title', $story->title.' — EPIC World Admin')

@section('content')
    <a href="{{ route('admin.stories.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to stories</a>

    <h1 class="mb-2 mt-2 text-xl font-semibold">{{ $story->title }}</h1>

    <div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
            {{ $story->status->label() }}
        </span>
        <span>{{ $story->topic?->name ?? 'Unclassified' }}</span>
        @if ($story->topic?->category)
            <span>/ {{ $story->topic->category->name }}</span>
        @endif
        <span>&middot; importance {{ $story->importance ?? '—' }}</span>
        @if ($sensitivity['sensitive'])
            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                Sensitive ({{ $sensitivity['reason'] }})
            </span>
        @endif
    </div>

    @if ($story->summary)
        <p class="mb-6 text-slate-700">{{ $story->summary }}</p>
    @endif

    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Fact sheet</h2>

        @if ($factSheet->isEmpty())
            <p class="text-sm text-slate-500">No extracted facts recorded for this story.</p>
        @else
            <div class="space-y-3">
                @foreach ($factSheet as $fact)
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">
                            Source: {{ $fact->sourceName }}
                        </div>
                        <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                            @foreach ($fact->reported as $field => $value)
                                <div>
                                    <dt class="text-slate-400">{{ $field }}</dt>
                                    <dd class="text-slate-800">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
            Source provenance ({{ $story->sources->count() }})
        </h2>

        @if ($story->sources->isEmpty())
            <p class="text-sm text-slate-500">No sources recorded.</p>
        @else
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Source</th>
                            <th class="px-4 py-2">Reported title</th>
                            <th class="px-4 py-2">Published</th>
                            <th class="px-4 py-2">Discovered</th>
                            <th class="px-4 py-2">Trusted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($story->sources as $source)
                            <tr>
                                <td class="px-4 py-2 font-medium text-slate-900">{{ $source->name }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $source->pivot->title ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $source->pivot->published_at ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $source->pivot->discovered_at ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $source->is_trusted ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($story->article)
        <a href="{{ route('admin.articles.edit', $story->article) }}"
           class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Open article editor
        </a>
    @else
        <p class="text-sm text-slate-500">No article has been drafted for this story yet.</p>
    @endif
@endsection
