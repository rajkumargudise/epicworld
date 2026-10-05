@extends('layouts.public')

@php($context = 'account')

@section('content')
    @php($editing = $article->exists)
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-3xl font-extrabold tracking-tight">{{ $editing ? 'Edit your post' : 'Write a post' }}</h1>
        <p class="mb-8 text-sm text-muted">Save a draft any time. When you submit, our editors review it &mdash; nothing is published until it is approved. See the <a class="text-accent hover:underline" href="{{ route('write') }}" target="_blank">writing guidelines</a>.</p>

        @include('partials.flash')

        <form method="POST" action="{{ $editing ? route('account.posts.update', $article) : route('account.posts.store') }}" class="space-y-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div>
                <label for="title" class="mb-1 block text-sm font-semibold">Title</label>
                <input id="title" name="title" type="text" value="{{ old('title', $article->title) }}" required minlength="10" maxlength="150"
                       class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-3 text-ink outline-none focus:border-accent">
            </div>

            <div>
                <label for="category_id" class="mb-1 block text-sm font-semibold">Category</label>
                <select id="category_id" name="category_id" required class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-3 text-ink outline-none focus:border-accent">
                    <option value="">Choose&hellip;</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $article->category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="dek" class="mb-1 block text-sm font-semibold">One-line summary <span class="font-normal text-muted">(optional)</span></label>
                <input id="dek" name="dek" type="text" value="{{ old('dek', $article->dek) }}" maxlength="300"
                       class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-3 text-ink outline-none focus:border-accent">
            </div>

            <div>
                <label for="content" class="mb-1 block text-sm font-semibold">Your post</label>
                <textarea id="content" name="content" rows="18" required minlength="600" maxlength="30000"
                          class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-3 font-mono text-sm leading-relaxed text-ink outline-none focus:border-accent">{{ old('content', $article->content) }}</textarea>
                <p class="mt-2 text-xs text-muted">
                    Formatting tips: start a line with <code>## </code> for a section heading, <code>- </code> for bullet points, <code>&gt; </code> for a quote.
                    Separate paragraphs with a blank line. **bold** and *italic* work too. Plain text only &mdash; no HTML.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" name="action" value="submit" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Submit for review</button>
                <button type="submit" name="action" value="draft" class="chip rounded-full px-6 py-3 text-sm font-semibold">Save draft</button>
                <a href="{{ route('account.dashboard') }}" class="rounded-full px-6 py-3 text-sm font-semibold text-muted hover:text-ink">Cancel</a>
            </div>
        </form>

        @if ($editing)
            <form method="POST" action="{{ route('account.posts.destroy', $article) }}" class="mt-10" onsubmit="return confirm('Delete this draft?')">
                @csrf @method('DELETE')
                <button class="text-sm text-red-400 hover:underline">Delete this draft</button>
            </form>
        @endif
    </div>
@endsection
