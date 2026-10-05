{{-- Approved comments + the submission form. Expects $article, $comments, $commentToken. --}}
@if ($site->commentsEnabled())
    <section id="comments" class="story-card mt-8 rounded-3xl p-6 sm:p-8" aria-labelledby="comments-h">
        <h2 id="comments-h" class="mb-1 text-xl font-extrabold tracking-tight">
            Comments @if ($comments->isNotEmpty())<span class="text-muted">({{ $comments->count() }})</span>@endif
        </h2>
        <p class="mb-6 text-sm text-muted">Comments are reviewed before they appear. Please keep it respectful and on topic.</p>

        @if (session('comment_status'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300" role="status">{{ session('comment_status') }}</div>
        @endif
        @if (session('comment_error'))
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300" role="alert">{{ session('comment_error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300" role="alert">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($comments->isNotEmpty())
            <ul class="mb-8 space-y-4">
                @foreach ($comments as $comment)
                    <li class="rounded-2xl border border-line bg-surface-2 p-4">
                        <div class="mb-1 flex items-center gap-2 text-sm">
                            <span class="font-semibold">{{ $comment->author_name }}</span>
                            <span class="text-muted">&middot; {{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="whitespace-pre-line break-words text-[0.95rem] leading-relaxed text-ink-soft">{{ $comment->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('comments.store', $article) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="ct" value="{{ $commentToken }}">
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="author_name" class="mb-1 block text-sm font-medium">Name</label>
                    <input id="author_name" name="author_name" type="text" required maxlength="80" value="{{ old('author_name', auth()->user()?->name) }}"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
                <div>
                    <label for="author_email" class="mb-1 block text-sm font-medium">Email <span class="text-muted">(never shown)</span></label>
                    <input id="author_email" name="author_email" type="email" required maxlength="150" value="{{ old('author_email', auth()->user()?->email) }}"
                           class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">
                </div>
            </div>
            <div>
                <label for="body" class="mb-1 block text-sm font-medium">Comment</label>
                <textarea id="body" name="body" rows="4" required minlength="3" maxlength="2000"
                          class="w-full rounded-xl border border-line-strong bg-surface-2 px-4 py-2.5 text-sm text-ink outline-none focus:border-accent">{{ old('body') }}</textarea>
            </div>
            <button type="submit" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Post comment</button>
        </form>
    </section>
@endif
