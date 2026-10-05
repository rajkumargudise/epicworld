<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryTemplateTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_structured_content_renders_as_numbered_cards_with_toc_and_takeaways(): void
    {
        $article = $this->publishedArticle([
            'content' => "Opening paragraph that sets the scene for readers.\n\n## What happened\n\nThe first section opens with a clear and complete sentence. More.\n\n## Why it matters\n\nThe second section also opens with a clear and complete sentence. More.\n\n- A point\n- Another point",
            'editorial_metadata' => ['key_points' => ['Takeaway one', 'Takeaway two']],
        ]);

        $this->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('Key takeaways')
            ->assertSee('Takeaway one')
            ->assertSee('In this story')
            ->assertSee('id="what-happened"', false)
            ->assertSee('section-why', false)
            ->assertSee('class="checklist"', false);
    }

    public function test_a_plain_imported_post_still_renders_without_errors(): void
    {
        $article = $this->publishedArticle(['content' => "One.\n\nTwo.\n\nThree.\n\nFour.\n\nFive.\n\nSix.\n\nSeven."]);

        $this->get(route('article.show', $article))->assertOk()->assertSee('Part 1');
    }

    public function test_the_home_page_renders_the_live_wire_and_lead_story(): void
    {
        $this->publishedArticle(['title' => 'Lead story headline']);

        $this->get('/')->assertOk()->assertSee('Live wire')->assertSee('Lead story headline');
    }
}
