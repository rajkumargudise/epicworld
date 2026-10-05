@extends('layouts.public')

@php($context = 'auth')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="story-card rounded-3xl p-8">
            <h1 class="text-2xl font-extrabold tracking-tight">Join EPIC World</h1>
            <p class="mt-1 text-sm text-muted">Create a contributor account to submit your own posts. Every post is reviewed by our editors before it is published.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                {{-- Honeypot: hidden from people, tempting to bots. --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label for="company_website">Company website</label>
                    <input id="company_website" type="text" name="company_website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Your name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" maxlength="80"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Password <span class="text-muted">(10+ characters, letters and numbers)</span></label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" minlength="10"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <label class="flex items-start gap-2 text-sm text-muted">
                    <input type="checkbox" name="accept_terms" value="1" class="mt-1" required>
                    <span>I agree to the <a href="{{ route('terms') }}" class="text-accent hover:underline" target="_blank">Terms</a> and <a href="{{ route('privacy') }}" class="text-accent hover:underline" target="_blank">Privacy Policy</a>, and I confirm the posts I submit are my own work.</span>
                </label>
                <button type="submit" class="btn-primary w-full rounded-full px-4 py-3 text-sm font-semibold">Create account</button>
            </form>

            <p class="mt-6 text-center text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-accent hover:underline">Log in</a></p>
        </div>
    </div>
@endsection
