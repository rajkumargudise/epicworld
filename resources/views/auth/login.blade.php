@extends('layouts.public')

@php
    $context = 'auth';
    $seoTitle = 'Log in';
    $seoDescription = 'Log in to EPIC World.';
    $canonicalUrl = route('login');
    $indexable = false;
@endphp

@section('content')
    <div class="mx-auto max-w-md">
        <div class="story-card rounded-3xl p-8">
            <h1 class="text-2xl font-extrabold tracking-tight">Log in</h1>
            <p class="mt-1 text-sm text-muted">EPIC World Admin &amp; contributor sign-in</p>

            @if ($errors->any())
                <div class="mt-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <label class="flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <button type="submit" class="btn-primary w-full rounded-full px-4 py-3 text-sm font-semibold">Log in</button>
            </form>

            <p class="mt-6 text-center text-sm text-muted">New here? <a href="{{ route('register') }}" class="font-semibold text-accent hover:underline">Create a contributor account</a></p>
        </div>
    </div>
@endsection
