<?php

namespace App\Services\NewsWire;

use App\Models\WireItem;
use Illuminate\Support\Collection;

/**
 * "Hot stories": developments that several different outlets are reporting
 * at once. Recent headlines are grouped by how many meaningful words they
 * share; a group with 2+ distinct sources is a hot story, ranked by number
 * of outlets (India gets a small boost) then recency.
 */
class HotStories
{
    private const STOP = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'are', 'was', 'were', 'has', 'have', 'had', 'its', 'his', 'her', 'their', 'into', 'over', 'after', 'about', 'says', 'said', 'will', 'new', 'who', 'what', 'how', 'why', 'than', 'but', 'not', 'out', 'amid', 'also', 'more', 'live', 'updates'];

    /**
     * @return Collection<int, array{lead: WireItem, count: int, sources: array<int, string>, items: Collection}>
     */
    public function top(int $limit = 6, int $hours = 24, float $similarity = 0.4): Collection
    {
        $items = WireItem::articles()
            ->where('published_at', '>=', now()->subHours($hours))
            ->newest()
            ->limit(400)
            ->get();

        return $this->cluster($items, $similarity)
            ->filter(fn (array $c) => $c['count'] >= 2)
            ->sortByDesc(fn (array $c) => $c['score'])
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, WireItem>  $items  newest first
     * @return Collection<int, array<string, mixed>>
     */
    public function cluster(Collection $items, float $similarity = 0.4): Collection
    {
        $clusters = [];

        foreach ($items as $item) {
            $tokens = $this->tokens($item->title);

            if (count($tokens) < 3) {
                continue;
            }

            $placed = false;

            foreach ($clusters as &$cluster) {
                if ($this->jaccard($tokens, $cluster['tokens']) >= $similarity) {
                    $cluster['items'][] = $item;
                    $placed = true;

                    break;
                }
            }
            unset($cluster);

            if (! $placed) {
                $clusters[] = ['tokens' => $tokens, 'items' => [$item]];
            }
        }

        return collect($clusters)->map(function (array $cluster) {
            $items = collect($cluster['items']);
            $sources = $items->pluck('source')->unique()->values();
            $lead = $items->first(fn (WireItem $i) => $i->image_url) ?? $items->first();
            $india = $items->contains(fn (WireItem $i) => $i->scope === 'news');
            $ageHours = max(0, $lead->published_at->diffInMinutes(now()) / 60);

            return [
                'lead' => $lead,
                'count' => $sources->count(),
                'sources' => $sources->all(),
                'items' => $items,
                'score' => $sources->count() * 10 + ($india ? 5 : 0) - $ageHours * 0.3,
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $title): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true))));
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : count(array_intersect($a, $b)) / $union;
    }
}
