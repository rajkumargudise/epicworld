@extends('layouts.admin')

@section('title', 'Edit article — EPIC World Admin')

@section('content')
    @php
        $metadata = $article->editorial_metadata ?? [];
        $generationMethod = $metadata['generation_method'] ?? null;
        $humanEditedAt = $metadata['human_edited_at'] ?? null;
        $sensitivity = $metadata['sensitivity'] ?? null;
        $quality = $metadata['quality'] ?? null;
    @endphp

    @if ($article->story)
        <a href="{{ route('admin.stories.show', $article->story) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to story</a>
    @endif

    <div class="mb-6 mt-2 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold">Edit article</h1>
        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
            Status: {{ $article->status->label() }}
        </span>
    </div>

    {{-- Editorial provenance: what generated this content, and whether a
         human has since edited it - never the underlying prompts, API
         keys, or provider internals that produced it. --}}
    <div class="mb-6 flex flex-wrap gap-2 text-xs">
        @if ($generationMethod === 'ai_assisted')
            <span class="inline-flex rounded-full bg-indigo-100 px-2 py-1 font-medium text-indigo-800">AI-generated draft</span>
        @elseif ($generationMethod === 'deterministic_template')
            <span class="inline-flex rounded-full bg-sky-100 px-2 py-1 font-medium text-sky-800">Deterministic template</span>
        @endif

        @if ($humanEditedAt)
            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 font-medium text-emerald-800">
                Human-edited {{ \Illuminate\Support\Carbon::parse($humanEditedAt)->diffForHumans() }}
            </span>
        @endif

        @if ($sensitivity && $sensitivity['sensitive'])
            @if ($sensitivity['human_reviewed_at'])
                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 font-medium text-emerald-800">
                    Sensitive content — reviewed {{ \Illuminate\Support\Carbon::parse($sensitivity['human_reviewed_at'])->diffForHumans() }}
                </span>
            @else
                <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-800">
                    Sensitive content — review required ({{ $sensitivity['reason'] }})
                </span>
            @endif
        @endif

        @if ($quality && ! $quality['passed'])
            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 font-medium text-red-800">
                Fails quality checks: {{ implode(', ', $quality['issues']) }}
            </span>
        @endif
    </div>

    {{-- Free image --}}
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center gap-4">
            @if ($article->featured_image)
                <img src="{{ $article->featured_image }}" alt="" referrerpolicy="no-referrer" class="h-20 w-32 rounded-md object-cover">
                <p class="text-xs text-slate-500">{{ $metadata['image_credit']['text'] ?? 'Image set manually' }}</p>
            @else
                <p class="text-sm text-slate-500">No featured image yet.</p>
            @endif
            <form method="POST" action="{{ route('admin.articles.image', $article) }}" class="ml-auto flex flex-wrap items-center gap-2">
                @csrf
                <input type="text" name="query" placeholder="Search words, e.g. classroom students" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" name="replace" value="1" {{ $article->featured_image ? '' : 'disabled' }}> replace current</label>
                <button class="rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Find a free image</button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.articles.update', $article) }}" class="mb-8 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700">Title</label>
            <input id="title" type="text" name="title" value="{{ old('title', $article->title) }}" required
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label for="dek" class="block text-sm font-medium text-slate-700">Dek / excerpt (standfirst)</label>
            <textarea id="dek" name="dek" rows="2"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('dek', $article->dek) }}</textarea>
        </div>

        <div>
            <label for="excerpt" class="block text-sm font-medium text-slate-700">Excerpt</label>
            <textarea id="excerpt" name="excerpt" rows="2"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('excerpt', $article->excerpt) }}</textarea>
        </div>

        <div>
            <label for="content" class="block text-sm font-medium text-slate-700">Content</label>
            <textarea id="content" name="content" rows="16" required
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm">{{ old('content', $article->content) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="category_id" class="block text-sm font-medium text-slate-700">Category</label>
                <select id="category_id" name="category_id"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $article->category_id) == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="tags" class="block text-sm font-medium text-slate-700">Tags (comma-separated)</label>
                <input id="tags" type="text" name="tags"
                    value="{{ old('tags', $article->tags->pluck('name')->implode(', ')) }}"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div>
            <label for="featured_image" class="block text-sm font-medium text-slate-700">Featured image URL</label>
            <input id="featured_image" type="text" name="featured_image" value="{{ old('featured_image', $article->featured_image) }}"
                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="seo_title" class="block text-sm font-medium text-slate-700">SEO title</label>
                <input id="seo_title" type="text" name="seo_title" value="{{ old('seo_title', $article->seo_title) }}"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="seo_description" class="block text-sm font-medium text-slate-700">SEO description</label>
                <input id="seo_description" type="text" name="seo_description" value="{{ old('seo_description', $article->seo_description) }}"
                    class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Save changes
        </button>
    </form>

    <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-6">
        @can('confirmSensitiveReview', $article)
            @if ($sensitivity && $sensitivity['sensitive'] && ! $sensitivity['human_reviewed_at'])
                <form method="POST" action="{{ route('admin.articles.confirmSensitiveReview', $article) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500">
                        Confirm sensitive-content review
                    </button>
                </form>
            @endif
        @endcan

        @can('approve', $article)
            <form method="POST" action="{{ route('admin.articles.approve', $article) }}">
                @csrf
                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Approve &amp; schedule
                </button>
            </form>
        @endcan

        @can('publish', $article)
            <form method="POST" action="{{ route('admin.articles.publish', $article) }}">
                @csrf
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    Publish
                </button>
            </form>
        @endcan
    </div>
@endsection
