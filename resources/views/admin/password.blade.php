@extends('layouts.admin')

@section('title', 'Change password — EPIC World Admin')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Change password</h1>

    <form method="POST" action="{{ route('admin.password.update') }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="current_password" class="mb-1 block text-sm font-medium">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">New password (12+ characters)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="12"
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>

        <button type="submit" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">Update password</button>
    </form>
@endsection
