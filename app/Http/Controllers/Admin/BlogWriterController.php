<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\WireItem;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use App\Services\Editorial\AiBlogGenerator;
use App\Services\Images\ArticleImageAttacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The editor's "write me a full blog on X" tool. The result is a Draft or
 * Review article (never published) opened straight in the article editor.
 */
class BlogWriterController extends Controller
{
    public function create(AiProviderManager $ai): View
    {
        $this->authorize('viewAny', Article::class);

        return view('admin.blog-writer', [
            'categories' => Category::active()->where('slug', '!=', 'latest')->orderBy('name')->get(),
            'aiReady' => $ai->isReady(),
        ]);
    }

    public function store(Request $request, AiBlogGenerator $generator): RedirectResponse
    {
        $this->authorize('update', new Article);

        $data = $request->validate([
            'topic' => ['required', 'string', 'min:8', 'max:300'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'length' => ['required', 'in:medium,long,deep'],
        ]);

        // A long post can take a minute or two to write.
        set_time_limit(240);

        try {
            $article = $generator->generate(
                topic: trim($data['topic']),
                category: Category::findOrFail($data['category_id']),
                notes: array_filter(preg_split('/\R/', (string) ($data['notes'] ?? '')) ?: []),
                length: $data['length'],
                extraMetadata: ['requested_by' => $request->user()->id],
            );
        } catch (AiProviderUnavailableException $e) {
            return back()->withInput()->with('error', 'The AI service is unavailable right now ('.$e->getMessage().'). Check the API key / credit in Settings and try again.');
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Could not write the post: '.$e->getMessage());
        }

        return redirect()->route('admin.articles.edit', $article)
            ->with('status', 'Your blog was written. Read it through, edit anything you like, then approve it from the Review queue.');
    }

    /**
     * Turn a live-desk headline into a full original article (Draft/Review,
     * never published) with the headline + summary as its source notes.
     */
    public function fromWire(Request $request, WireItem $item, AiBlogGenerator $generator): RedirectResponse
    {
        $this->authorize('update', new Article);
        abort_unless($item->kind === 'article', 404);

        $slug = $item->scope === 'world' ? 'world' : 'india';
        $category = Category::active()->where('slug', $slug)->first() ?? Category::active()->where('slug', '!=', 'latest')->first();

        set_time_limit(240);

        try {
            $article = $generator->generate(
                topic: $item->title,
                category: $category,
                notes: array_filter([$item->title, $item->summary, 'Reported by '.$item->source.' on '.$item->published_at->format('j F Y')]),
                length: 'long',
                sourceLinks: [['name' => $item->source, 'url' => $item->url]],
                extraMetadata: ['requested_by' => $request->user()->id, 'wire_item_id' => $item->id],
            );
        } catch (AiProviderUnavailableException $e) {
            return back()->with('error', 'The AI service is unavailable right now ('.$e->getMessage().'). Check the key / credit in Settings.');
        } catch (Throwable $e) {
            return back()->with('error', 'Could not write the article: '.$e->getMessage());
        }

        return redirect()->route('admin.articles.edit', $article)->with('status', 'Full article drafted from the headline. Read it, edit if needed, then approve it from the Review queue.');
    }

    /**
     * Pick a free, credited image for an article that has none (or replace
     * the current one with a fresh search).
     */
    public function image(Request $request, Article $article, ArticleImageAttacher $images): RedirectResponse
    {
        $this->authorize('update', $article);

        $data = $request->validate(['query' => ['nullable', 'string', 'max:100'], 'replace' => ['nullable', 'boolean']]);

        if ($request->boolean('replace')) {
            $article->update(['featured_image' => null]);
        }

        if ($images->attach($article->refresh(), $data['query'] ?? null)) {
            return back()->with('status', 'Free image added with its credit.');
        }

        return back()->with('error', 'No suitable free image found. Try different words, or add a Pexels/Unsplash key in Settings for better results.');
    }
}
