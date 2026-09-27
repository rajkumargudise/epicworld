@extends('layouts.admin')

@section('title', 'Stories — EPIC World Admin')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Story queue</h1>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Topic / Category</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Importance</th>
                    <th class="px-4 py-3">Sensitive</th>
                    <th class="px-4 py-3">Sources</th>
                    <th class="px-4 py-3">First seen</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stories as $story)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $story->title }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $story->topic?->name ?? '—' }}
                            @if ($story->topic?->category)
                                <span class="text-slate-400">/ {{ $story->topic->category->name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                {{ $story->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $story->importance ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($sensitivity[$story->id]['sensitive'])
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                    Sensitive ({{ $sensitivity[$story->id]['reason'] }})
                                </span>
                            @else
                                <span class="text-slate-400">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $story->sources_count }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $story->first_seen_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.stories.show', $story) }}" class="font-medium text-slate-900 underline">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No stories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $stories->links() }}
    </div>
@endsection
