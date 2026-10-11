<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\WireItem;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use App\Services\NewsWire\HotStories;
use App\Services\NewsWire\WireReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Writes the full in-site report for the most important fresh headlines: stories several
 * outlets are covering first, then India, then local. Runs every minute from the scheduler
 * with a small limit, so reports appear steadily without bursting the AI quota. Readers who
 * open a headline that has no report yet get one written on demand (see LiveNewsController).
 */
class WriteWireReports extends Command
{
    public const COOLDOWN_KEY = 'wire:reports:cooldown';

    protected $signature = 'wire:write-reports {--limit=2 : Reports to write in this run} {--id= : Write one specific item}';

    protected $description = 'Write full EpicWorld reports for the top live-desk headlines.';

    public function handle(WireReportWriter $writer, AiProviderManager $ai, HotStories $hotStories): int
    {
        if (! $ai->isReady()) {
            $this->warn('No AI provider is configured.');

            return self::SUCCESS;
        }

        if (Cache::has(self::COOLDOWN_KEY)) {
            $this->warn('AI provider is cooling down after a rate limit.');

            return self::SUCCESS;
        }

        if (self::capReached()) {
            $this->warn('Daily report cap reached.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $items = $this->option('id')
            ? WireItem::query()->whereKey((int) $this->option('id'))->get()
            : $this->candidates($hotStories);

        $written = 0;

        foreach ($items as $item) {
            if ($written >= $limit) {
                break;
            }

            if (! $writer->eligible($item)) {
                continue;
            }

            $lock = Cache::lock(self::lockKey($item->id), 300);

            if (! $lock->get()) {
                continue;
            }

            try {
                $ok = $writer->write($item->refresh());
                $this->line(($ok ? 'OK   ' : 'FAIL ').'#'.$item->id.' '.$item->title.($ok ? '' : ' - '.$item->report_error));
                $written += $ok ? 1 : 0;
            } catch (AiProviderUnavailableException $e) {
                Cache::put(self::COOLDOWN_KEY, true, now()->addMinutes(5));
                $this->warn('AI unavailable: '.$e->getMessage());

                break;
            } finally {
                $lock->release();
            }
        }

        $this->info("Wrote {$written} report(s).");

        return self::SUCCESS;
    }

    public static function lockKey(int $id): string
    {
        return 'wire:report:'.$id;
    }

    public static function capReached(): bool
    {
        $cap = (int) (Setting::read('ai_daily_report_cap') ?? config('newswire.reports.daily_cap', 300));

        return WireItem::query()->where('report_generated_at', '>=', now()->subDay())->count() >= $cap;
    }

    /**
     * Most important first: stories several outlets cover, then each background scope's newest.
     *
     * @return \Illuminate\Support\Collection<int, WireItem>
     */
    private function candidates(HotStories $hotStories)
    {
        $ids = $hotStories->top(12)->map(fn (array $cluster) => $cluster['lead']->id)->values();

        $since = now()->subHours((int) config('newswire.reports.max_age_hours', 48));

        foreach ((array) config('newswire.reports.background_scopes', ['news', 'local']) as $scope) {
            $ids = $ids->merge(
                WireItem::articles()
                    ->where('scope', $scope)
                    ->where('published_at', '>=', $since)
                    ->whereNull('report')
                    ->where(fn ($q) => $q->whereNull('report_status')->orWhere('report_status', 'pending'))
                    ->where('report_attempts', '<', 3)
                    ->newest()
                    ->limit(8)
                    ->pluck('id')
            );
        }

        $ids = $ids->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $byId = WireItem::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => $byId[$id] ?? null)->filter()->values();
    }
}
