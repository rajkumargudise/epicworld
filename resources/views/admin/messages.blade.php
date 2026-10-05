@extends('layouts.admin')

@section('title', 'Messages — EPIC World Admin')

@section('content')
    <h1 class="mb-6 text-xl font-semibold">Contact messages</h1>

    @if ($messages->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-slate-500">No messages yet.</p>
    @else
        <div class="space-y-3">
            @foreach ($messages as $message)
                <div class="rounded-lg border bg-white p-4 {{ $message->read_at ? 'border-slate-200' : 'border-indigo-300' }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">{{ $message->subject }} @unless ($message->read_at)<span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-normal text-indigo-800">New</span>@endunless</p>
                            <p class="text-sm text-slate-500">{{ $message->name }} &lt;<a class="underline" href="mailto:{{ $message->email }}">{{ $message->email }}</a>&gt; &middot; {{ $message->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex gap-3 text-sm">
                            <form method="POST" action="{{ route('admin.messages.read', $message) }}">@csrf @method('PATCH')<button class="text-slate-600 underline">{{ $message->read_at ? 'Mark unread' : 'Mark read' }}</button></form>
                            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?')">@csrf @method('DELETE')<button class="text-red-600 underline">Delete</button></form>
                        </div>
                    </div>
                    <p class="mt-3 whitespace-pre-line break-words text-sm text-slate-800">{{ $message->message }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $messages->links() }}</div>
    @endif
@endsection
