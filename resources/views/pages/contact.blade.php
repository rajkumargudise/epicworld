@extends('layouts.public')

@php($context = 'page')

@section('content')
    <div class="mx-auto max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-wider text-accent">Contact</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">Get in touch</h1>
        <p class="mt-3 text-ink-soft">Corrections, story tips, partnerships or just hello &mdash; send us a message and we'll read it.
            @if ($site->contactEmail())
                You can also email <a class="text-accent hover:underline" href="mailto:{{ $site->contactEmail() }}">{{ $site->contactEmail() }}</a>.
            @endif
        </p>

        <div class="mt-8">@include('partials.flash')</div>

        <form method="POST" action="{{ route('contact.send') }}" class="story-card space-y-4 rounded-3xl p-6 sm:p-8">
            @csrf
            <input type="hidden" name="ct" value="{{ $formToken }}">
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="80" class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="150" class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
            </div>
            <div>
                <label for="subject" class="mb-1 block text-sm font-medium">Subject</label>
                <input id="subject" name="subject" type="text" value="{{ old('subject') }}" required maxlength="150" class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
            </div>
            <div>
                <label for="message" class="mb-1 block text-sm font-medium">Message</label>
                <textarea id="message" name="message" rows="6" required minlength="10" maxlength="4000" class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Send message</button>
        </form>
    </div>
@endsection
