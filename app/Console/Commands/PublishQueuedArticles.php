<?php

namespace App\Console\Commands;

use App\Services\Editorial\PublishQueue;
use Illuminate\Console\Command;

class PublishQueuedArticles extends Command
{
    protected $signature = 'articles:publish-queue';

    protected $description = 'Publish the next approved article when the daily slot arrives (one per day).';

    public function handle(PublishQueue $queue): int
    {
        $article = $queue->publishDue();

        $this->info($article ? "Published #{$article->id}: {$article->title}" : 'Nothing to publish right now.');

        return self::SUCCESS;
    }
}
