@extends('layouts.admin')

@section('title', 'Source Feeds — EPIC World Admin')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Source Feeds</h1>

    @if ($feeds->isEmpty())
        <p class="text-slate-500">No source feeds are configured yet.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Feed</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last fetched</th>
                        <th class="px-4 py-3">Last success</th>
                        <th class="px-4 py-3">Last failure</th>
                        <th class="px-4 py-3">Current error</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($feeds as $feed)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $feed->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $feed->source->name }}</td>
                            <td class="px-4 py-3">
                                @if ($feed->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $feed->last_fetched_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $feed->last_success_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                @if ($feed->last_failure_at)
                                    <span class="text-red-700">{{ $feed->last_failure_at->diffForHumans() }}</span>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-slate-600" title="{{ $feed->last_error }}">
                                {{ $feed->last_error ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
