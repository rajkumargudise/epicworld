<?php

namespace App\Enums;

/**
 * The editorial operations the AI provider abstraction can be asked
 * to perform. Deliberately small: add a case only when a real
 * editorial service needs it (Milestone 8 adds article generation
 * consumption), never speculatively.
 */
enum AiOperation: string
{
    case ArticleGeneration = 'article_generation';

    public function label(): string
    {
        return match ($this) {
            self::ArticleGeneration => 'Article Generation',
        };
    }
}
