{{-- Newsletter sign-up card. Optional $source ('home' | 'article'). --}}
@php $src = $source ?? 'home'; @endphp
<section id="subscribe" class="relative isolate overflow-hidden rounded-3xl border border-line bg-surface p-6 sm:p-8" aria-labelledby="subscribe-h-{{ $src }}">
    <div class="hero-glow absolute inset-0 -z-10"></div>
    <div class="grid items-center gap-5 lg:grid-cols-2">
        <div>
            <h2 id="subscribe-h-{{ $src }}" class="text-2xl font-extrabold tracking-tight sm:text-3xl">Never miss a <span class="gradient-text">great read</span></h2>
            <p class="mt-2 text-sm text-ink-soft">One email with our best new guides on health, AI, money and what is happening in India. No spam.</p>
        </div>
        @if (session('subscribe_status'))
            <p class="rounded-2xl border border-line-strong bg-surface-2 px-5 py-4 text-sm font-medium text-ink" role="status">{{ session('subscribe_status') }}</p>
        @else
            <form method="POST" action="{{ route('subscribe.store') }}" class="flex flex-col gap-3 sm:flex-row">
                @csrf
                <input type="hidden" name="source" value="{{ $src }}">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                <label for="subscribe-email-{{ $src }}" class="sr-only">Email address</label>
                <input id="subscribe-email-{{ $src }}" type="email" name="email" required maxlength="150" placeholder="you@example.com" autocomplete="email"
                       class="min-h-[48px] flex-1 rounded-full border border-line-strong bg-bg px-5 text-sm text-ink placeholder:text-muted focus:border-accent focus:outline-none">
                <button type="submit" class="btn-primary min-h-[48px] rounded-full px-6 text-sm font-bold">Subscribe</button>
            </form>
            @error('email')<p class="text-sm text-red-400 lg:col-span-2">{{ $message }}</p>@enderror
        @endif
    </div>
</section>
