<?php

namespace Tests\Unit;

use App\Services\Content\StoryRenderer;
use PHPUnit\Framework\TestCase;

class StoryRendererTest extends TestCase
{
    private function render(string $content, ?array $keyPoints = null): array
    {
        return (new StoryRenderer)->render($content, $keyPoints);
    }

    public function test_headings_become_numbered_sections_with_a_lead_and_toc(): void
    {
        $r = $this->render("Opening paragraph of the story.\n\n## What happened\n\nFirst fact here. More detail.\n\n## Why it matters\n\nIt changes things. Really.");

        $this->assertSame('Opening paragraph of the story.', $r['lead']);
        $this->assertCount(2, $r['sections']);
        $this->assertSame('01', $r['sections'][0]['number']);
        $this->assertSame('what-happened', $r['sections'][0]['id']);
        $this->assertSame('why', $r['sections'][1]['variant']);
        $this->assertSame(['what-happened', 'why-it-matters'], array_column($r['toc'], 'id'));
    }

    public function test_a_body_without_headings_is_split_into_chapters(): void
    {
        $paras = implode("\n\n", array_map(fn ($i) => "Paragraph number {$i} is here.", range(1, 9)));

        $r = $this->render($paras);

        $this->assertGreaterThan(1, count($r['sections']));
        $this->assertSame('Part 1', $r['sections'][0]['title']);
    }

    public function test_short_unpunctuated_lines_in_imported_text_become_headings(): void
    {
        $r = $this->render("This is the opening paragraph of an imported post that has real length to it.\n\nChallenges in the Indian Education System\n\nA long paragraph describing the challenges in considerable detail so that it is clearly body text.\n\nThe Role of Technology\n\nAnother long paragraph describing how technology is changing classrooms across the country today.");

        $this->assertSame(['Challenges in the Indian Education System', 'The Role of Technology'], array_column($r['toc'], 'title'));
    }

    public function test_a_short_headingless_body_is_one_untitled_section(): void
    {
        $r = $this->render("Lead paragraph.\n\nOnly one more paragraph.");

        $this->assertCount(1, $r['sections']);
        $this->assertSame('', $r['sections'][0]['title']);
    }

    public function test_lists_and_quotes_are_recognised(): void
    {
        $r = $this->render("Lead.\n\n## Details\n\n- first point\n- second point\n\n> A memorable line.\n\n1. one\n2. two");

        $types = array_column($r['sections'][0]['blocks'], 'type');
        $this->assertSame(['ul', 'quote', 'ol'], $types);
    }

    public function test_html_in_the_body_is_escaped_and_cannot_inject_markup(): void
    {
        $r = $this->render("<script>alert(1)</script> lead.\n\n## <img src=x onerror=alert(1)>\n\nBody with <b>tags</b> and [bad](javascript:alert(1)).");

        $json = json_encode($r);
        $this->assertStringNotContainsString('<script>', $r['lead']);
        $this->assertStringContainsString('&lt;script&gt;', $r['lead']);
        $this->assertStringNotContainsString('href="javascript', $json);
        $this->assertStringContainsString('&lt;b&gt;', $r['sections'][0]['blocks'][0]['html']);
    }

    public function test_inline_bold_italic_and_https_links_work(): void
    {
        $r = $this->render('**Bold** and *italic* and [site](https://example.com/x).');

        $this->assertStringContainsString('<strong>Bold</strong>', $r['lead']);
        $this->assertStringContainsString('<em>italic</em>', $r['lead']);
        $this->assertStringContainsString('<a href="https://example.com/x"', $r['lead']);
    }

    public function test_explicit_key_points_win_and_otherwise_are_derived_from_sections(): void
    {
        $body = "Lead.\n\n## One\n\nThe first section opens with a clear and complete sentence. Extra.\n\n## Two\n\nThe second section also opens with a clear and complete sentence. Extra.";

        $explicit = $this->render($body, ['Point A', 'Point B']);
        $this->assertSame(['Point A', 'Point B'], $explicit['takeaways']);

        $derived = $this->render($body);
        $this->assertCount(2, $derived['takeaways']);
        $this->assertStringContainsString('first section opens', $derived['takeaways'][0]);
    }
}
