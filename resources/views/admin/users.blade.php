@extends('layouts.admin')

@section('title', 'Users — EPIC World Admin')

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Users</h1>
    <p class="mb-6 text-sm text-slate-500">Contributors can submit posts for review. Editors can review and approve; only admins publish and manage settings.</p>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Published</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is(auth()->user()))
                                {{ $user->role }} <span class="text-slate-400">(you)</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex items-center gap-2">
                                    @csrf @method('PUT')
                                    <select name="role" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                                        @foreach (['contributor', 'editor', 'admin'] as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                                        @endforeach
                                    </select>
                                    <button class="text-slate-600 underline">Save</button>
                                </form>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->published_posts }}</td>
                        <td class="px-4 py-3">
                            @if ($user->isSuspended())
                                <span class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">Suspended</span>
                            @else
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="suspend" value="{{ $user->isSuspended() ? 0 : 1 }}">
                                    <button class="underline {{ $user->isSuspended() ? 'text-emerald-700' : 'text-red-600' }}">{{ $user->isSuspended() ? 'Unsuspend' : 'Suspend' }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
