@if (session('status'))
    <div class="admin-alert admin-alert-ok mb-6 rounded-xl border px-4 py-3 text-sm">{{ session('status') }}</div>
@endif

@if (session('error'))
    <div class="admin-alert admin-alert-bad mb-6 rounded-xl border px-4 py-3 text-sm">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="admin-alert admin-alert-bad mb-6 rounded-xl border px-4 py-3 text-sm">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
