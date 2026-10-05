@extends('layouts.admin')

@section('title', 'Comments — EPIC World Admin')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Comments</h1>
    <p class="mb-5 text-sm text-slate-500">Comments never appear on the site until you approve them.</p>

    <div class="mb-5 flex flex-wrap gap-2 text-sm">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'spam' => 'Spam'] as $key => $label)
            <a href="{{ route('admin.comments.index', ['status' => $key]) }}"
               class="rounded-full border px-4 py-1.5 {{ $status === $key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                {{ $label }} ({{ $counts[$key] ?? 0 }})
            </a>
        @endforeach
    </div>

    @if ($comments->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-slate-500">Nothing here.</p>
    @else
        <form method="POST" action="{{ route('admin.comments.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <button name="action" value="approve" class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">Approve</button>
                <button name="action" value="reject" class="rounded-md bg-slate-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Reject</button>
                <button name="action" value="spam" class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">Mark spam</button>
                <button name="action" value="delete" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700" onclick="return confirm('Delete the selected comments permanently?')">Delete</button>
                <label class="ml-2 flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)"> Select all
                </label>
            </div>

            <div class="space-y-3">
                @foreach ($comments as $comment)
                    <div class="flex gap-3 rounded-lg border border-slate-200 bg-white p-4">
                        <input type="checkbox" name="ids[]" value="{{ $comment->id }}" class="mt-1">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 text-sm">
                                <span class="font-semibold">{{ $comment->author_name }}</span>
                                <span class="text-slate-500">&lt;{{ $comment->author_email }}&gt; &middot; {{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="whitespace-pre-line break-words text-sm text-slate-800">{{ $comment->body }}</p>
                            <p class="mt-2 text-xs text-slate-500">On: <a class="underline" href="{{ route('article.show', $comment->article) }}" target="_blank">{{ $comment->article?->title }}</a></p>
                        </div>
                    </div>
                @endforeach
            </div>
        </form>
        <div class="mt-4">{{ $comments->links() }}</div>
    @endif
@endsection
