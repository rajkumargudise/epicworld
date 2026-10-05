<?php

namespace App\Services\Content;

use Illuminate\Support\Str;

/**
 * Turns an article's plain-text body into the card structure the
 * "Epic Story" reading template renders. Nothing here emits raw
 * input: every piece of text is escaped first, and only a tiny,
 * explicit inline syntax (**bold**, *italic*, [label](https://url))
 * is turned back into markup afterwards, so a body can never inject
 * HTML or script.
 *
 * Supported block syntax (one block per blank-line-separated chunk):
 *   "## Heading"     starts a new section card
 *   "- item" lines   a checklist card
 *   "1. item" lines  a numbered list
 *   "> text"         a pull quote
 *   anything else    a paragraph
 *
 * A body with no headings at all (e.g. a plain imported post) is
 * split into "chapters" of a few paragraphs each so it still reads as
 * a sequence of cards rather than one long wall of text.
 */
class StoryRenderer
{
    private const CHAPTER_PARAGRAPHS = 3;

    private const MAX_TAKEAWAYS = 4;

    /**
     * @return array{lead: ?string, sections: array<int, array<string, mixed>>, toc: array<int, array{id: string, title: string}>, takeaways: array<int, string>, word_count: int}
     */
    public function render(string $content, ?array $keyPoints = null): array
    {
        $blocks = $this->parseBlocks($content);

        $lead = null;
        if (($blocks[0]['type'] ?? null) === 'p') {
            $lead = $blocks[0]['html'];
            array_shift($blocks);
        }

        $sections = $this->group($blocks);

        $toc = [];
        foreach ($sections as $i => $section) {
            $toc[] = ['id' => $section['id'], 'title' => $section['title']];
        }

        return [
            'lead' => $lead,
            'sections' => $sections,
            'toc' => $toc,
            'takeaways' => $this->takeaways($keyPoints, $sections),
            'word_count' => str_word_count(strip_tags(preg_replace('/[#>*\-\[\]()]/', ' ', $content) ?? '')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseBlocks(string $content): array
    {
        $chunks = preg_split('/\R{2,}/', trim(str_replace("\r\n", "\n", $content))) ?: [];
        $blocks = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^#{1,3}\s+(.+)$/s', $chunk, $m) && ! str_contains(trim($m[1]), "\n")) {
                $blocks[] = ['type' => 'h', 'text' => trim($m[1])];

                continue;
            }

            // A heading directly followed by text in the same chunk.
            if (preg_match('/^#{1,3}\s+([^\n]+)\n(.+)$/s', $chunk, $m)) {
                $blocks[] = ['type' => 'h', 'text' => trim($m[1])];
                $chunk = trim($m[2]);
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", $chunk)), fn ($l) => $l !== ''));

            if ($lines !== [] && collect($lines)->every(fn ($l) => preg_match('/^[-*•]\s+/', $l))) {
                $blocks[] = ['type' => 'ul', 'items' => array_map(fn ($l) => $this->inline(preg_replace('/^[-*•]\s+/', '', $l)), $lines)];
            } elseif ($lines !== [] && collect($lines)->every(fn ($l) => preg_match('/^\d+[.)]\s+/', $l))) {
                $blocks[] = ['type' => 'ol', 'items' => array_map(fn ($l) => $this->inline(preg_replace('/^\d+[.)]\s+/', '', $l)), $lines)];
            } elseif (str_starts_with($lines[0] ?? '', '>')) {
                $text = trim(implode(' ', array_map(fn ($l) => ltrim($l, '> '), $lines)));
                $blocks[] = ['type' => 'quote', 'html' => $this->inline($text)];
            } else {
                $blocks[] = ['type' => 'p', 'html' => $this->inline(implode(' ', $lines)), 'text' => implode(' ', $lines)];
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function group(array $blocks): array
    {
        $hasHeadings = collect($blocks)->contains(fn ($b) => $b['type'] === 'h');
        $sections = [];
        $current = null;
        $usedIds = [];

        $open = function (string $title, bool $generated) use (&$sections, &$current, &$usedIds) {
            if ($current !== null) {
                $sections[] = $current;
            }

            $id = Str::slug($title) ?: 'section';
            $base = $id;
            $n = 1;
            while (in_array($id, $usedIds, true)) {
                $id = $base.'-'.(++$n);
            }
            $usedIds[] = $id;

            $current = [
                'id' => $id,
                'title' => $title,
                'generated' => $generated,
                'variant' => $this->variant($title),
                'blocks' => [],
            ];
        };

        if ($hasHeadings) {
            foreach ($blocks as $block) {
                if ($block['type'] === 'h') {
                    $open($block['text'], false);

                    continue;
                }

                if ($current === null) {
                    $open('The story', true);
                }

                $current['blocks'][] = $block;
            }
        } else {
            $count = 0;
            $chapter = 0;
            foreach ($blocks as $block) {
                if ($current === null || ($block['type'] === 'p' && $count >= self::CHAPTER_PARAGRAPHS)) {
                    $chapter++;
                    $open('Part '.$chapter, true);
                    $count = 0;
                }

                $current['blocks'][] = $block;
                if ($block['type'] === 'p') {
                    $count++;
                }
            }
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        // A single generated section needs no heading or numbering.
        if (count($sections) === 1 && $sections[0]['generated']) {
            $sections[0]['title'] = '';
        }

        foreach ($sections as $i => &$section) {
            $section['number'] = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        }

        return $sections;
    }

    private function variant(string $title): string
    {
        $t = Str::lower($title);

        return match (true) {
            str_contains($t, 'why it matters'), str_contains($t, 'why this matters'), str_contains($t, 'significance') => 'why',
            str_contains($t, "what's next"), str_contains($t, 'what next'), str_contains($t, 'what to watch'), str_contains($t, 'outlook') => 'next',
            str_contains($t, 'background'), str_contains($t, 'context') => 'context',
            default => 'default',
        };
    }

    /**
     * @param  array<int, string>|null  $keyPoints
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, string>
     */
    private function takeaways(?array $keyPoints, array $sections): array
    {
        $explicit = collect($keyPoints ?? [])
            ->filter(fn ($p) => is_string($p) && trim($p) !== '')
            ->map(fn ($p) => $this->inline(trim($p)))
            ->take(self::MAX_TAKEAWAYS)
            ->values()
            ->all();

        if ($explicit !== []) {
            return $explicit;
        }

        // Derived: the first sentence of each section's first paragraph.
        $derived = [];
        foreach ($sections as $section) {
            foreach ($section['blocks'] as $block) {
                if ($block['type'] === 'p') {
                    $sentence = $this->firstSentence($block['text'] ?? '');
                    if ($sentence !== '') {
                        $derived[] = e($sentence);
                    }

                    break;
                }
            }

            if (count($derived) >= self::MAX_TAKEAWAYS) {
                break;
            }
        }

        // One section can't summarise itself - skip a pointless card.
        return count($derived) >= 2 ? $derived : [];
    }

    private function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\*\*|\*|\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? '');

        if (preg_match('/^(.{30,220}?[.!?])(\s|$)/u', $text, $m)) {
            return $m[1];
        }

        return Str::limit($text, 200, '…');
    }

    /**
     * Escape, then re-enable only bold, italic and http(s) links.
     */
    private function inline(string $text): string
    {
        $html = e($text);

        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            fn ($m) => '<a href="'.$m[2].'" rel="nofollow noopener" target="_blank">'.$m[1].'</a>',
            $html,
        ) ?? $html;

        return $html;
    }
}
