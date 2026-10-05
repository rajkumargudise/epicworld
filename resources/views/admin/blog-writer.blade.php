@extends('layouts.admin')

@section('title', 'Blog writer — EPIC World Admin')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Blog writer</h1>
    <p class="mb-6 max-w-2xl text-sm text-slate-500">
        Describe a topic and the AI writes a complete blog post &mdash; opening, sections, key takeaways and a conclusion &mdash; with a free, credited image.
        It lands in your review queue as a draft; nothing is published until you approve it. Writing takes about a minute.
    </p>

    @unless ($aiReady)
        <div class="mb-6 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            No AI key is configured for the selected provider. Add one in <a href="{{ route('admin.settings.edit') }}" class="underline">Settings</a> first.
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.blog-writer.store') }}" class="max-w-2xl space-y-5" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Writing… this takes about a minute';">
        @csrf

        <div>
            <label for="topic" class="mb-1 block text-sm font-medium">Topic or working title</label>
            <input id="topic" name="topic" type="text" required minlength="8" maxlength="300" value="{{ old('topic') }}"
                   placeholder="e.g. How UPI changed everyday payments in India"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="category_id" class="mb-1 block text-sm font-medium">Category</label>
                <select id="category_id" name="category_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="length" class="mb-1 block text-sm font-medium">Length</label>
                <select id="length" name="length" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="medium" @selected(old('length') === 'medium')>Medium (700–1,000 words)</option>
                    <option value="long" @selected(old('length', 'long') === 'long')>Long (1,000–1,500 words)</option>
                    <option value="deep" @selected(old('length') === 'deep')>Deep dive (1,500–2,200 words)</option>
                </select>
            </div>
        </div>

        <div>
            <label for="notes" class="mb-1 block text-sm font-medium">Facts / notes to include <span class="font-normal text-slate-500">(optional, one per line)</span></label>
            <textarea id="notes" name="notes" rows="6" maxlength="4000" placeholder="Paste any verified facts, figures or angles you want covered. The AI will not invent specifics beyond these."
                      class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-60" @disabled(! $aiReady)>Write the blog</button>
    </form>
@endsection
