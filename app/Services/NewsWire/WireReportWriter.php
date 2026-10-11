<?php

namespace App\Services\NewsWire;

use App\Enums\AiOperation;
use App\Models\WireItem;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\AiRequest;
use App\Services\Ai\Exceptions\AiProviderUnavailableException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the full, original EpicWorld report for a live-desk headline, so readers get the
 * whole story on this site instead of a two-sentence brief and a link elsewhere.
 *
 * What goes in: the headline and summary, whatever extra text the feed supplied, the text
 * of the published report (read privately - never stored or shown), and what other outlets
 * reported about the same development. What comes out: an original article in EpicWorld's
 * own words, with event-specific facts limited to that material and only widely known
 * background added. A copy guard rejects any draft that reuses long runs of the source's
 * wording, so the site never republishes another outlet's text.
 */
class WireReportWriter
{
    public const SCHEMA = [
        'dek' => ['type' => 'string', 'max_length' => 300, 'required' => false],
        'body' => ['type' => 'string'],
        'key_points' => ['type' => 'array', 'required' => false],
    ];

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly AiProviderManager $providers,
        private readonly ArticleTextExtractor $pages,
        private readonly HotStories $hotStories,
    ) {}

    public function eligible(WireItem $item): bool
    {
        return $item->kind === 'article'
            && ! $item->hasReport()
            && $item->report_status !== 'failed'
            && $item->report_attempts < self::MAX_ATTEMPTS
            && $item->published_at->gt(now()->subHours((int) config('newswire.reports.max_age_hours', 48)));
    }

    /**
     * @throws AiProviderUnavailableException when the AI provider is rate limited / down - the
     *                                        caller should stop and try again later.
     */
    public function write(WireItem $item): bool
    {
        if (! $this->eligible($item)) {
            return $item->hasReport();
        }

        @set_time_limit(240);

        $item->forceFill(['report_status' => 'pending', 'report_attempts' => $item->report_attempts + 1])->save();

        $page = $this->pages->fetch($item->url);
        $related = $this->hotStories->related($item);

        $material = $this->material($item, $page, $related);

        $request = new AiRequest(
            operation: AiOperation::ArticleGeneration,
            storyId: 0,
            facts: $material['facts'],
            instructions: $this->instructions($item, $item->report_attempts > 1),
            schema: self::SCHEMA,
            allowBackground: true,
        );

        try {
            $result = $this->providers->resolve()->respond($request);
        } catch (Throwable $e) {
            return $this->fail($item, 'AI call failed: '.Str::limit($e->getMessage(), 150, ''));
        }

        if (in_array($result->status->value, ['rate_limited', 'authentication_error', 'timeout'], true)) {
            // Not this story's fault - give the attempt back.
            $item->forceFill(['report_status' => null, 'report_attempts' => max(0, $item->report_attempts - 1)])->save();

            throw new AiProviderUnavailableException((string) $result->error);
        }

        if (! $result->successful()) {
            return $this->fail($item, Str::limit(sprintf('%s: %s', $result->status->value, $result->error), 240, ''));
        }

        $body = $this->clean((string) ($result->data['body'] ?? ''));
        $words = str_word_count(strip_tags($body));

        if ($words < (int) config('newswire.reports.min_words', 200)) {
            return $this->fail($item, "Report too short ({$words} words).");
        }

        $overlap = $this->overlap($body, $material['texts']);

        if ($overlap > (float) config('newswire.reports.max_overlap', 0.12)) {
            return $this->fail($item, sprintf('Too close to the original wording (%d%% copied).', round($overlap * 100)));
        }

        $item->forceFill([
            'report' => $body,
            'report_status' => 'ready',
            'report_error' => null,
            'report_generated_at' => now(),
            'report_meta' => [
                'dek' => isset($result->data['dek']) ? Str::limit(trim((string) $result->data['dek']), 300, '') : null,
                'key_points' => collect($result->data['key_points'] ?? [])
                    ->filter(fn ($p) => is_string($p) && trim($p) !== '')
                    ->map(fn ($p) => mb_substr(trim($p), 0, 280))->take(4)->values()->all(),
                'words' => $words,
                'read_page' => $page !== null,
                'outlets' => $related->pluck('source')->push($item->source)->unique()->values()->all(),
                'provider' => $result->provider,
                'model' => $result->model,
            ],
        ])->save();

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WireItem>  $related
     * @return array{facts: array<int, string>, texts: array<int, string>}
     */
    private function material(WireItem $item, ?string $page, $related): array
    {
        $facts = [
            'Headline: '.$item->title,
            sprintf('Reported by %s on %s (%s).', $item->source, $item->published_at->timezone('Asia/Kolkata')->format('j F Y, g:i A'), 'IST'),
        ];
        $texts = [$item->title];

        if ($item->summary) {
            $facts[] = 'Summary from '.$item->source.': '.$item->summary;
            $texts[] = $item->summary;
        }

        if ($item->source_text) {
            $facts[] = 'Further reporting from '.$item->source.': '.Str::limit($item->source_text, 6000, '');
            $texts[] = $item->source_text;
        }

        if ($page) {
            $facts[] = 'Text of the published report ('.$item->source.'): '.Str::limit($page, 9000, '');
            $texts[] = $page;
        }

        foreach ($related as $other) {
            $facts[] = sprintf('Also reported by %s: %s%s', $other->source, $other->title, $other->summary ? ' - '.$other->summary : '');
            $texts[] = $other->title.' '.$other->summary;
        }

        return ['facts' => $facts, 'texts' => $texts];
    }

    private function instructions(WireItem $item, bool $stricter): string
    {
        $scope = config('newswire.scopes.'.$item->scope.'.label', 'news');

        return 'You are a news writer for EpicWorld, an Indian news website. Write a complete, original news report ('.$scope.' desk) about the development in the supplied material, '
            .'entirely in your own words, for a general reader who will read it here and nowhere else. '
            .'Length: 450 to 750 words when the material is rich; if it is thin, write 250 to 450 words and do not pad or speculate. '
            .'Ground every event-specific claim (who, what, when, where, numbers, quotes, names) strictly in the supplied material and attribute it naturally '
            .'("According to The Hindu...", "Officials said..."); never invent a detail, number, quote, name or date, and never present a claim as fact if the material does not support it. '
            .'You may add widely known background, definitions and context in general terms, clearly separate from the new facts. '
            .'Do NOT copy sentences or distinctive phrases from the material - rewrite and restructure everything'
            .($stricter ? ' (a previous draft reused too much of the original wording: paraphrase far more aggressively and change the sentence structure)' : '').'. '
            .'Format the body as plain text: a strong opening paragraph (no heading) that states the news in two or three sentences, then sections each starting with a line like '
            .'"## What happened", "## Key details", "## Background", "## Why it matters", "## What happens next" (use only the sections the material supports), ending with "## Bottom line". '
            .'Separate paragraphs with a blank line; use "- " lines for lists. Do not mention that you are an AI or refer to "the material" or "the source text". '
            .'Also return: dek (a 140-160 character summary sentence) and key_points (3 or 4 short standalone takeaway sentences).';
    }

    private function clean(string $body): string
    {
        $body = trim(preg_replace("/\r\n?/", "\n", $body) ?? $body);

        return preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;
    }

    /**
     * Share of the report's 8-word runs that also appear verbatim in the reference material.
     *
     * @param  array<int, string>  $texts
     */
    private function overlap(string $report, array $texts): float
    {
        $reference = [];

        foreach ($texts as $text) {
            foreach ($this->shingles($text) as $hash) {
                $reference[$hash] = true;
            }
        }

        $mine = $this->shingles($report);

        if ($mine === []) {
            return 0.0;
        }

        return count(array_filter($mine, fn (string $hash) => isset($reference[$hash]))) / count($mine);
    }

    /**
     * @return array<int, string>
     */
    private function shingles(string $text, int $size = 8): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];

        for ($i = 0, $n = count($words) - $size + 1; $i < $n; $i++) {
            $out[] = md5(implode(' ', array_slice($words, $i, $size)));
        }

        return $out;
    }

    private function fail(WireItem $item, string $error): bool
    {
        $item->forceFill([
            'report_status' => $item->report_attempts >= self::MAX_ATTEMPTS ? 'failed' : null,
            'report_error' => Str::limit($error, 240, ''),
        ])->save();

        return false;
    }
}
