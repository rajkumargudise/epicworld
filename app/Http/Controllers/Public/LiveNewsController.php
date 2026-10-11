<?php

namespace App\Http\Controllers\Public;

use App\Console\Commands\WriteWireReports;
use App\Http\Controllers\Controller;
use App\Models\WireItem;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use App\Services\Content\StoryRenderer;
use App\Services\NewsWire\NewsWireFetcher;
use App\Services\NewsWire\WireReportWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The live desk: world / news / local headlines and video, straight
 * from the wire with no editorial queue. A visitor request also
 * refreshes the wire (after the response is sent) when it has gone
 * stale, so the page stays current even before the scheduler cron is
 * running on the host.
 */
class LiveNewsController extends Controller
{
    private const PER_PAGE = 30;

    public function index(?string $scope = null): View
    {
        $this->refreshIfStale();

        $scopes = config('newswire.scopes');
        $isVideos = $scope === 'videos';

        if ($scope === null) {
            $sections = collect($scopes)->map(fn ($meta, $key) => [
                'key' => $key,
                'meta' => $meta,
                'items' => WireItem::articles()->where('scope', $key)->newest()->take(10)->get(),
            ]);

            return view('live.index', [
                'mode' => 'hub',
                'sections' => $sections,
                'videos' => WireItem::videos()->newest()->take(8)->get(),
                'channels' => config('newswire.live_channels'),
                ...$this->seo('Live news - world, India & local', 'Live headlines and video from around the world, across India and in Hyderabad & Telangana, updated every few minutes.', route('live')),
            ]);
        }

        abort_unless($isVideos || isset($scopes[$scope]), 404);

        $query = $isVideos ? WireItem::videos() : WireItem::articles()->where('scope', $scope);
        $meta = $isVideos
            ? ['label' => 'Videos', 'blurb' => 'Live TV and the latest video reports from newsrooms around the world.']
            : $scopes[$scope];

        return view('live.index', [
            'mode' => 'scope',
            'scope' => $scope,
            'meta' => $meta,
            'items' => $query->newest()->paginate(self::PER_PAGE)->withQueryString(),
            'videos' => $isVideos ? collect() : WireItem::videos()->where('scope', $scope)->newest()->take(4)->get(),
            'channels' => collect(config('newswire.live_channels'))
                ->when(! $isVideos, fn ($c) => $c->where('scope', $scope))
                ->values()
                ->all(),
            ...$this->seo($meta['label'].' news - live', $meta['blurb'], route('live.scope', $scope)),
        ]);
    }

    /**
     * HTML fragments the page polls every minute to stay current.
     */
    public function feed(string $scope): Response
    {
        $scopes = config('newswire.scopes');
        abort_unless(isset($scopes[$scope]), 404);

        $this->refreshIfStale();

        $variant = in_array(request()->query('variant'), ['panel', 'compact'], true) ? request()->query('variant') : 'list';
        $limit = match ($variant) { 'panel' => 8, 'compact' => 6, default => 12 };

        $items = WireItem::articles()->where('scope', $scope)->newest()->take($limit)->get();

        return response(view('live.'.($variant === 'compact' ? '_list' : '_'.$variant), ['items' => $items, 'scope' => $scope, 'summary' => $variant !== 'compact'])->render())
            ->header('Cache-Control', 'public, max-age=30')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function show(Request $request, WireItem $item, ?string $slug = null): View
    {
        $generating = $item->kind === 'article' && ! $item->hasReport() && $this->startReport($request, $item);

        $related = WireItem::query()
            ->where('id', '!=', $item->id)
            ->where('kind', $item->kind)
            ->where('scope', $item->scope)
            ->newest()
            ->take(6)
            ->get();

        return view('live.show', [
            'item' => $item,
            'layout' => $item->hasReport()
                ? app(StoryRenderer::class)->render((string) $item->report, $item->report_meta['key_points'] ?? null)
                : null,
            'generating' => $generating || $this->isWriting($item),
            'related' => $related,
            // Short wire briefs are credited summaries, not original
            // articles - keep them out of search indexes.
            ...$this->seo($item->title, (string) ($item->summary ?? $item->title), $item->path(), false),
        ]);
    }

    /**
     * Lets the page poll for its report while it is being written.
     */
    public function report(WireItem $item): JsonResponse
    {
        $status = $item->hasReport() ? 'ready' : ($item->report_status === 'failed' ? 'failed' : ($this->isWriting($item) ? 'pending' : 'none'));

        return response()->json(['status' => $status])->header('Cache-Control', 'no-store');
    }

    private function isWriting(WireItem $item): bool
    {
        return $item->report_status === 'pending' && $item->updated_at?->gt(now()->subMinutes(6));
    }

    /**
     * A reader opened a headline that has no report yet: write it now, after the response is
     * sent, so the page can show "being written" and swap in the report when it is ready.
     * Skipped for crawlers (the scheduled writer covers them), when the AI provider is cooling
     * down or unconfigured, and once the daily cap is reached.
     */
    private function startReport(Request $request, WireItem $item): bool
    {
        $agent = (string) $request->userAgent();

        if ($agent === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|http-client|headless/i', $agent)) {
            return false;
        }

        $writer = app(WireReportWriter::class);

        if (! $writer->eligible($item) || Cache::has(WriteWireReports::COOLDOWN_KEY) || WriteWireReports::capReached() || ! app(AiProviderManager::class)->isReady()) {
            return false;
        }

        // Exactly one request wins the right to write (a stale "pending" can be reclaimed).
        $claimed = WireItem::query()->whereKey($item->id)
            ->where(fn ($q) => $q->whereNull('report_status')->orWhere(fn ($q) => $q->where('report_status', 'pending')->where('updated_at', '<', now()->subMinutes(6))))
            ->update(['report_status' => 'pending', 'updated_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $id = $item->id;

        dispatch(function () use ($id, $writer) {
            $fresh = WireItem::query()->find($id);

            if (! $fresh) {
                return;
            }

            try {
                $writer->write($fresh);
            } catch (AiProviderUnavailableException) {
                Cache::put(WriteWireReports::COOLDOWN_KEY, true, now()->addMinutes(5));
            } catch (\Throwable $e) {
                Log::warning('On-demand wire report failed: '.$e->getMessage());
                $fresh->forceFill(['report_status' => null, 'report_error' => Str::limit($e->getMessage(), 240, '')])->save();
            }
        })->afterResponse();

        return true;
    }

    private function refreshIfStale(): void
    {
        if (! config('newswire.enabled')) {
            return;
        }

        $fetcher = app(NewsWireFetcher::class);

        if (! $fetcher->isStale()) {
            return;
        }

        dispatch(function () use ($fetcher) {
            $lock = Cache::lock(NewsWireFetcher::LOCK_KEY, 300);

            if ($lock->get()) {
                try {
                    $fetcher->run();
                } finally {
                    $lock->release();
                }
            }
        })->afterResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function seo(string $title, string $description, string $canonical, bool $indexable = true): array
    {
        return [
            'seoTitle' => $title.' - EPIC World',
            'seoDescription' => mb_substr($description, 0, 160),
            'canonicalUrl' => $canonical,
            'indexable' => $indexable,
        ];
    }
}
