<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Services\Editorial\PublicationPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Article editor and the three domain-service-backed actions
 * (approve, publish, confirm sensitive review). This controller never
 * writes ArticleStatus itself, and never reads a "status" field from
 * a request: every status transition happens by calling
 * PublicationPolicy, which is the only place that may move an
 * Article forward. update() only ever touches editorial content
 * fields.
 */
class ArticleController extends Controller
{
    public function __construct(
        private readonly PublicationPolicy $publicationPolicy,
    ) {}

    public function edit(Article $article): View
    {
        $this->authorize('view', $article);

        $article->load(['story', 'category', 'tags']);

        return view('admin.articles.edit', [
            'article' => $article,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Saves editorial content only. "status" is never read from the
     * request, whatever the browser sends - the only way an
     * Article's status changes is approve(), publish(), or the
     * domain services from earlier milestones.
     */
    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'dek' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $tags = collect(explode(',', (string) ($validated['tags'] ?? '')))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->values();

        $metadata = $article->editorial_metadata ?? [];
        $metadata['human_edited_at'] = now()->toIso8601String();
        $metadata['human_edited_by'] = $request->user()->name;

        $article->fill([
            'title' => $validated['title'],
            'dek' => $validated['dek'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'category_id' => $validated['category_id'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'featured_image' => $validated['featured_image'] ?? null,
            'editorial_metadata' => $metadata,
        ]);

        if ($article->author_id === null) {
            $article->author_id = $request->user()->id;
        }

        $article->save();

        $article->tags()->sync($tags->map(function (string $name) {
            return Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id;
        }));

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('status', 'Article saved.');
    }

    public function approve(Article $article): RedirectResponse
    {
        $this->authorize('approve', $article);

        if ($this->publicationPolicy->approve($article)) {
            return back()->with('status', 'Article approved and scheduled.');
        }

        return back()->with('error', $this->explainBlockage($article->refresh(), 'approved'));
    }

    public function publish(Article $article): RedirectResponse
    {
        $this->authorize('publish', $article);

        if ($this->publicationPolicy->publish($article)) {
            return back()->with('status', 'Article published.');
        }

        return back()->with('error', $this->explainBlockage($article->refresh(), 'published'));
    }

    public function confirmSensitiveReview(Article $article): RedirectResponse
    {
        $this->authorize('confirmSensitiveReview', $article);

        $this->publicationPolicy->confirmSensitiveReview($article);

        return back()->with('status', 'Sensitive-content review confirmed.');
    }

    /**
     * A human-readable reason a PublicationPolicy call refused,
     * drawn from the same editorial_metadata it just recorded -
     * never a generic "something went wrong".
     */
    private function explainBlockage(Article $article, string $action): string
    {
        $sensitivity = $article->editorial_metadata['sensitivity'] ?? null;

        if (($sensitivity['sensitive'] ?? false) && ($sensitivity['human_reviewed_at'] ?? null) === null) {
            $reason = $sensitivity['reason'] ?? 'flagged';

            return "This article could not be {$action}: it covers sensitive content ({$reason}) and requires sensitive-review confirmation first.";
        }

        $issues = $article->editorial_metadata['quality']['issues'] ?? [];

        if ($issues !== []) {
            return "This article could not be {$action}: it fails quality checks (".implode(', ', $issues).').';
        }

        return "This article could not be {$action}: it is not in the required status for this action.";
    }
}
