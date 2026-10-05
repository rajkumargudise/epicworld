@extends('layouts.admin')

@section('title', 'Review queue — EPIC World Admin')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Review queue</h1>
    <p class="mb-6 max-w-2xl text-sm text-slate-500">
        Drafts written by the AI from collected reporting. Nothing goes live until you publish it. Open an article to read or edit it first;
        sensitive topics must be opened and confirmed individually.
    </p>

    @if ($articles->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-slate-500">Nothing is waiting for review.</p>
    @else
        <form method="POST" action="{{ route('admin.review.publish') }}">
            @csrf
            <div class="mb-3 flex items-center gap-3">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
                        onclick="return confirm('Approve and publish the selected articles?')">Approve &amp; publish selected</button>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]:not(:disabled)').forEach(c => c.checked = this.checked)"> Select all
                </label>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-10 px-4 py-3"></th>
                            <th class="px-4 py-3">Article</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Sources</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($articles as $article)
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $article->id }}" @disabled($article->needs_sensitive_review)>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ $article->title }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $article->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $article->story?->sources->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($article->needs_sensitive_review)
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Sensitive — confirm first</span>
                                    @else
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Ready</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.articles.edit', $article) }}" class="text-slate-600 underline hover:text-slate-900">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
@endsection
