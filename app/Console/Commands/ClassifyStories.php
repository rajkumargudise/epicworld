<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Story;
use App\Services\Editorial\PublicationDecision;
use App\Services\Editorial\StoryClassifier;
use Illuminate\Console\Command;

class ClassifyStories extends Command
{
    protected $signature = 'stories:classify';

    protected $description = 'Assign topics (and so categories) to stories that have none, and re-evaluate their drafts.';

    public function handle(StoryClassifier $classifier, PublicationDecision $decision): int
    {
        $classified = 0;
        $articles = 0;

        Story::query()->whereNull('topic_id')->chunkById(200, function ($stories) use ($classifier, $decision, &$classified, &$articles) {
            foreach ($stories as $story) {
                if (! $classifier->classify($story)) {
                    continue;
                }

                $classified++;
                $story->refresh();
                $article = $story->article;

                if ($article && $article->category_id === null && $story->topic?->category_id) {
                    $article->update(['category_id' => $story->topic->category_id]);
                    $decision->decide($article->refresh());
                    $articles++;
                }
            }
        });

        $this->info("Classified {$classified} stories; fixed {$articles} drafts.");

        return self::SUCCESS;
    }
}
