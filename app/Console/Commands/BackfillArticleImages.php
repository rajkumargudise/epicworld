<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\Images\ArticleImageAttacher;
use Illuminate\Console\Command;

class BackfillArticleImages extends Command
{
    protected $signature = 'images:backfill {--limit=30 : Maximum articles to process}';

    protected $description = 'Add a free, credited image to articles that have none.';

    public function handle(ArticleImageAttacher $attacher): int
    {
        $added = 0;
        $tried = 0;

        Article::query()
            ->whereNull('featured_image')
            ->orderByDesc('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (Article $article) use ($attacher, &$added, &$tried) {
                $tried++;
                $metadata = $article->editorial_metadata ?? [];
                $query = $metadata['image_query'] ?? $article->title;

                if ($attacher->attach($article, $query)) {
                    $added++;
                }
            });

        $this->info("Added images to {$added} of {$tried} articles.");

        return self::SUCCESS;
    }
}
