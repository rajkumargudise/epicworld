@extends('layouts.admin')

@section('title', 'Settings — EPIC World Admin')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">AI settings</h1>
    <p class="mb-6 max-w-2xl text-sm text-slate-500">
        Choose which AI writes your draft articles and store its API key. Keys are encrypted at rest and are never shown again after saving.
        Drafts still wait in the review queue until you approve and publish them.
    </p>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-2xl space-y-8">
        @csrf
        @method('PUT')

        <div>
            <label for="ai_provider" class="mb-1 block text-sm font-medium">AI provider</label>
            <select id="ai_provider" name="ai_provider" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="openai" @selected($provider === 'openai')>ChatGPT (OpenAI)</option>
                <option value="gemini" @selected($provider === 'gemini')>Gemini (Google)</option>
            </select>
        </div>

        <fieldset class="space-y-3 rounded-lg border border-slate-200 bg-white p-5">
            <legend class="px-1 text-sm font-semibold">OpenAI / ChatGPT</legend>
            <p class="text-xs {{ $openaiKeySet ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $openaiKeySet ? 'An API key is set.' : 'No API key set yet.' }}
            </p>
            <div>
                <label for="openai_api_key" class="mb-1 block text-sm font-medium">API key</label>
                <input id="openai_api_key" name="openai_api_key" type="password" autocomplete="off" placeholder="{{ $openaiKeySet ? 'Leave blank to keep the current key' : 'sk-…' }}"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="openai_model" class="mb-1 block text-sm font-medium">Model</label>
                <input id="openai_model" name="openai_model" type="text" value="{{ old('openai_model', $openaiModel) }}"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="clear_openai_api_key" value="1"> Remove the stored OpenAI key
            </label>
        </fieldset>

        <fieldset class="space-y-3 rounded-lg border border-slate-200 bg-white p-5">
            <legend class="px-1 text-sm font-semibold">Gemini</legend>
            <p class="text-xs {{ $geminiKeySet ? 'text-emerald-700' : 'text-slate-500' }}">
                {{ $geminiKeySet ? 'An API key is set.' : 'No API key set (optional).' }}
            </p>
            <div>
                <label for="gemini_api_key" class="mb-1 block text-sm font-medium">API key</label>
                <input id="gemini_api_key" name="gemini_api_key" type="password" autocomplete="off" placeholder="{{ $geminiKeySet ? 'Leave blank to keep the current key' : '' }}"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" name="clear_gemini_api_key" value="1"> Remove the stored Gemini key
            </label>
        </fieldset>

        <button type="submit" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">Save settings</button>
    </form>
@endsection
