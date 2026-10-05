<?php

namespace App\Http\Controllers\Account;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A signed-in member's own posts. A member can only ever see and edit
 * posts they wrote, and only while a post is a Draft: once submitted it
 * sits in the moderators' review queue and is locked, and nothing is
 * public until an administrator approves and publishes it. "status" is
 * never read from the request - the only transitions here are
 * "save as draft" and "submit for review", chosen by which button was
 * pressed.
 */
class ContributorPostController extends Controller
{
    private const MAX_IN_REVIEW = 5;

    public function dashboard(Request $request): View
    {
        $posts = Article::query()
            ->where('author_id', $request->user()->id)
            ->with('category')
            ->orderByDesc('updated_at')
            ->get();

        return view('account.dashboard', [
            'posts' => $posts,
            'seoTitle' => 'My posts',
            'seoDescription' => 'Your posts on EPIC World.',
            'canonicalUrl' => route('account.dashboard'),
            'indexable' => false,
        ]);
    }

    public function create(): View
    {
        return view('account.post-form', [
            'article' => new Article,
            'categories' => $this->categories(),
            'seoTitle' => 'Write a post',
            'seoDescription' => 'Write a post for EPIC World.',
            'canonicalUrl' => route('account.posts.create'),
            'indexable' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $submit = $request->input('action') === 'submit';

        if ($submit && $this->inReviewCount($request) >= self::MAX_IN_REVIEW) {
            return back()->withInput()->with('error', 'You already have '.self::MAX_IN_REVIEW.' posts waiting for review. Please wait for a decision before submitting more.');
        }

        $article = Article::create([
            'author_id' => $request->user()->id,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'dek' => $data['dek'] ?? null,
            'content' => $data['content'],
            'status' => $submit ? ArticleStatus::Review : ArticleStatus::Draft,
            'reading_time_minutes' => max(1, (int) ceil(str_word_count($data['content']) / 200)),
            'allow_indexing' => true,
            'editorial_metadata' => ['source' => 'contributor', 'submitted_at' => $submit ? now()->toIso8601String() : null],
        ]);

        return redirect()->route('account.dashboard')->with('status', $submit
            ? 'Thanks! Your post was submitted and is waiting for editor review.'
            : 'Draft saved. Submit it for review when you are ready.');
    }

    public function edit(Request $request, Article $article): View
    {
        $this->ensureOwnDraft($request, $article);

        return view('account.post-form', [
            'article' => $article,
            'categories' => $this->categories(),
            'seoTitle' => 'Edit post',
            'seoDescription' => 'Edit your post.',
            'canonicalUrl' => route('account.posts.edit', $article),
            'indexable' => false,
        ]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->ensureOwnDraft($request, $article);

        $data = $this->validated($request);
        $submit = $request->input('action') === 'submit';

        if ($submit && $this->inReviewCount($request) >= self::MAX_IN_REVIEW) {
            return back()->withInput()->with('error', 'You already have '.self::MAX_IN_REVIEW.' posts waiting for review.');
        }

        $metadata = $article->editorial_metadata ?? [];
        unset($metadata['rejection_reason']);
        $metadata['source'] = 'contributor';

        if ($submit) {
            $metadata['submitted_at'] = now()->toIso8601String();
        }

        $article->update([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'dek' => $data['dek'] ?? null,
            'content' => $data['content'],
            'status' => $submit ? ArticleStatus::Review : ArticleStatus::Draft,
            'reading_time_minutes' => max(1, (int) ceil(str_word_count($data['content']) / 200)),
            'editorial_metadata' => $metadata,
        ]);

        return redirect()->route('account.dashboard')->with('status', $submit
            ? 'Submitted for review.'
            : 'Draft updated.');
    }

    public function destroy(Request $request, Article $article): RedirectResponse
    {
        $this->ensureOwnDraft($request, $article);

        $article->delete();

        return redirect()->route('account.dashboard')->with('status', 'Draft deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:10', 'max:150', 'regex:/^[^<>]+$/u'],
            'dek' => ['nullable', 'string', 'max:300', 'regex:/^[^<>]*$/u'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'content' => ['required', 'string', 'min:600', 'max:30000'],
        ], [
            'content.min' => 'Please write at least 600 characters (about 100 words) so the editors have enough to review.',
            'title.regex' => 'The title may not contain < or > characters.',
            'dek.regex' => 'The summary may not contain < or > characters.',
        ]);
    }

    private function ensureOwnDraft(Request $request, Article $article): void
    {
        abort_unless(
            $article->author_id === $request->user()->id
            && $article->status === ArticleStatus::Draft
            && $article->isContributed(),
            404,
        );
    }

    private function inReviewCount(Request $request): int
    {
        return Article::query()
            ->where('author_id', $request->user()->id)
            ->where('status', ArticleStatus::Review)
            ->count();
    }

    private function categories()
    {
        return Category::active()->where('slug', '!=', 'latest')->orderBy('name')->get();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 80, '') ?: 'post';
        $slug = $base;

        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
