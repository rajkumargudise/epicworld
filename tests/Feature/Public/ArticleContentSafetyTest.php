<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 17: article content is authored as plain text - the admin
 * editor is a plain textarea, and AiArticleGenerator only ever produces
 * a plain prose string. Nothing in the editorial pipeline authors or
 * expects HTML. Before this milestone, the public article template
 * rendered $article->content with {!! !!} (raw, unescaped) - a stored
 * <script> tag (from a compromised source, a citation string, or an
 * editor's own input) would have executed in every visitor's browser.
 * Article::displayContentHtml() is the fix: it escapes first, then adds
 * paragraph/line-break structure around the already-safe text. These
 * tests prove the public page never lets stored content become live
 * markup, regardless of what produced it.
 */
class ArticleContentSafetyTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_a_script_tag_in_content_is_never_rendered_as_live_markup(): void
    {
        $article = $this->publishedArticle([
            'content' => "Some safe opening text.\n\n<script>alert(1)</script>\n\nMore safe text.",
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        // The escaped, inert form is fine to appear in the page source.
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_an_image_onerror_payload_in_content_is_never_rendered_as_live_markup(): void
    {
        $article = $this->publishedArticle([
            'content' => '<img src=x onerror=alert(1)>',
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_a_javascript_href_in_content_is_never_rendered_as_a_live_link(): void
    {
        $article = $this->publishedArticle([
            'content' => '<a href="javascript:alert(1)">click me</a>',
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertDontSee('<a href="javascript:alert(1)">', false);
    }

    public function test_plain_paragraphs_are_still_rendered_as_separate_paragraphs(): void
    {
        $article = $this->publishedArticle([
            'content' => "First paragraph of the story.\n\nSecond paragraph of the story.",
        ]);

        $response = $this->get(route('article.show', $article));

        $response->assertOk();
        $response->assertSee('<p>First paragraph of the story.</p>', false);
        $response->assertSee('<p>Second paragraph of the story.</p>', false);
    }

    public function test_the_admin_editor_also_never_renders_content_as_live_markup(): void
    {
        $user = User::factory()->editor()->create();
        $article = $this->publishedArticle([
            'status' => ArticleStatus::Draft,
            'published_at' => null,
            'content' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($user)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        // The editor textarea escapes its value via Blade's {{ }}, so
        // the stored markup appears only as inert, escaped text for
        // editing - never as a live script.
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
