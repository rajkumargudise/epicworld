@extends('layouts.public')

@php($context = 'account')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="story-card rounded-3xl p-8">
            <h1 class="text-2xl font-extrabold tracking-tight">Change password</h1>
            <div class="mt-4">@include('partials.flash')</div>
            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
                @csrf @method('PUT')
                @foreach ([['current_password', 'Current password', 'current-password'], ['password', 'New password (10+ characters, letters and numbers)', 'new-password'], ['password_confirmation', 'Confirm new password', 'new-password']] as [$name, $label, $auto])
                    <div>
                        <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="password" required autocomplete="{{ $auto }}"
                               class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                    </div>
                @endforeach
                <button type="submit" class="btn-primary w-full rounded-full px-4 py-3 text-sm font-semibold">Update password</button>
            </form>
            <p class="mt-4 text-center text-sm"><a class="text-muted hover:text-accent" href="{{ route('account.dashboard') }}">&larr; Back to my posts</a></p>
        </div>
    </div>
@endsection
