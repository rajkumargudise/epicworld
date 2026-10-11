<?php

namespace App\Services\Editorial;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The daily publishing queue. An editor approves an article (status Scheduled, no publish
 * date) and it joins the queue; at the daily slot (15:00 IST by default) the oldest approved
 * article goes live - but only if nothing else has already gone live that day, so posts that
 * were given a specific date keep their day and the queue fills the days in between.
 *
 * Nothing enters the queue except through PublicationPolicy::approve(), so every quality and
 * sensitive-content gate has already been passed by a person.
 */
class PublishQueue
{
    public const TIMEZONE = 'Asia/Kolkata';

    public function __construct(private readonly PublicationPolicy $policy) {}

    public static function time(): string
    {
        return (string) config('editorial.publish_time', '15:00');
    }

    /**
     * Approved articles waiting for a slot, in the order they were approved.
     *
     * @return Collection<int, Article>
     */
    public function waiting(): Collection
    {
        return Article::query()
            ->where('status', ArticleStatus::Scheduled)
            ->whereNull('published_at')
            ->get()
            ->sortBy([
                fn (Article $a, Article $b) => strcmp((string) ($a->editorial_metadata['approved_at'] ?? ''), (string) ($b->editorial_metadata['approved_at'] ?? '')),
                fn (Article $a, Article $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * Publishes the next queued article if today's slot has arrived and today has no post yet.
     */
    public function publishDue(): ?Article
    {
        $slot = $this->slot(now(self::TIMEZONE));

        if (now()->lt($slot)) {
            return null;
        }

        if (Article::query()->publiclyVisible()->where('published_at', '>=', $slot)->exists()) {
            return null;
        }

        $next = $this->waiting()->first();

        if ($next === null || ! $this->policy->publish($next)) {
            return null;
        }

        return $next->refresh();
    }

    /**
     * When each waiting article will go live, given the days already taken.
     *
     * @return Collection<int, array{article: Article, at: Carbon}>
     */
    public function projected(): Collection
    {
        $taken = Article::query()
            ->where('status', ArticleStatus::Published)
            ->where('published_at', '>=', $this->slot(now(self::TIMEZONE)))
            ->pluck('published_at')
            ->map(fn (Carbon $at) => $at->copy()->timezone(self::TIMEZONE)->toDateString())
            ->flip();

        $day = now(self::TIMEZONE)->startOfDay();

        // Today's slot is still ahead only if it has not passed yet.
        if (now()->gte($this->slot($day))) {
            $day->addDay();
        }

        $out = collect();

        foreach ($this->waiting() as $article) {
            while ($taken->has($day->toDateString())) {
                $day->addDay();
            }

            $out->push(['article' => $article, 'at' => $this->slot($day)->timezone(self::TIMEZONE)]);
            $day->addDay();
        }

        return $out;
    }

    /**
     * The publishing slot on the given (IST) day, as an instant.
     */
    private function slot(Carbon $day): Carbon
    {
        return Carbon::parse($day->copy()->timezone(self::TIMEZONE)->toDateString().' '.self::time(), self::TIMEZONE)->utc();
    }
}
