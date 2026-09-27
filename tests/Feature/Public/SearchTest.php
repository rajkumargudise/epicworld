<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use CreatesPublicFixtures;
    use RefreshDatabase;

    public function test_a_normal_search_returns_matching_published_articles(): void
    {
        $this->publishedArticle(['title' => 'Amazon launches new cloud feature']);
        $this->publishedArticle(['title' => 'Completely unrelated headline']);

        $response = $this->get(route('search', ['q' => 'cloud']));

        $response->assertOk();
        $response->assertSee('Amazon launches new cloud feature');
        $response->assertDontSee('Completely unrelated headline');
    }

    public function test_search_matches_on_title(): void
    {
        $article = $this->publishedArticle(['title' => 'Quantum computing breakthrough']);

        $response = $this->get(route('search', ['q' => 'quantum']));

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_search_matches_on_dek(): void
    {
        $article = $this->publishedArticle([
            'title' => 'A regular headline',
            'dek' => 'A story about semiconductor shortages worldwide',
        ]);

        $response = $this->get(route('search', ['q' => 'semiconductor']));

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_search_matches_on_excerpt(): void
    {
        $article = $this->publishedArticle([
            'title' => 'A regular headline',
            'dek' => null,
            'excerpt' => 'Covers the latest developments in battery chemistry',
        ]);

        $response = $this->get(route('search', ['q' => 'battery chemistry']));

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_search_matches_on_tag_name(): void
    {
        $article = $this->publishedArticle(['title' => 'An article about infrastructure']);
        $tag = $this->tag(['name' => 'Kubernetes']);
        $article->tags()->attach($tag->id);

        $unrelated = $this->publishedArticle(['title' => 'A different article']);

        $response = $this->get(route('search', ['q' => 'kubernetes']));

        $response->assertOk();
        $response->assertSee($article->title);
        $response->assertDontSee($unrelated->title);
    }

    public function test_search_matches_on_category_name(): void
    {
        $category = $this->category(['name' => 'Cybersecurity']);
        $article = $this->publishedArticle(['category_id' => $category->id, 'title' => 'A security story']);

        $response = $this->get(route('search', ['q' => 'cybersecurity']));

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_search_matches_on_topic_name_via_the_articles_story(): void
    {
        $topic = $this->topic(['name' => 'Space Exploration']);
        $article = $this->publishedArticleWithTopic($topic, ['title' => 'A rocket launch recap']);

        $response = $this->get(route('search', ['q' => 'space exploration']));

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_search_is_case_insensitive(): void
    {
        $article = $this->publishedArticle(['title' => 'Renewable Energy Milestone']);

        $response = $this->get(route('search', ['q' => 'RENEWABLE energy']));

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_an_empty_query_shows_a_prompt_rather_than_an_unrestricted_listing(): void
    {
        $this->publishedArticle(['title' => 'Should not appear in an empty search']);

        $response = $this->get(route('search'));

        $response->assertOk();
        $response->assertDontSee('Should not appear in an empty search');
        $response->assertSee('Enter a search term');
    }

    public function test_a_whitespace_only_query_is_treated_as_empty(): void
    {
        $this->publishedArticle(['title' => 'Should not appear for whitespace search']);

        $response = $this->get(route('search', ['q' => '   ']));

        $response->assertOk();
        $response->assertDontSee('Should not appear for whitespace search');
        $response->assertSee('Enter a search term');
    }

    public function test_search_results_paginate(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle([
                'title' => "Matching article {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('search', ['q' => 'matching']));

        $response->assertOk();
        $response->assertSee('Matching article 0');
        $response->assertDontSee('Matching article 24');
        $response->assertSee('page=2', false);
    }

    public function test_search_preserves_the_query_string_across_pagination_links(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle([
                'title' => "Preserve query {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->get(route('search', ['q' => 'preserve query']));

        $response->assertOk();
        $response->assertSee('q=preserve', false);
    }

    public function test_search_orders_deterministically_across_repeated_requests(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->publishedArticle([
                'title' => "Deterministic search {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $extract = fn (string $html) => preg_match_all('/Deterministic search \d+/', $html, $m) ? $m[0] : [];

        $first = $extract($this->get(route('search', ['q' => 'deterministic search']))->getContent());
        $second = $extract($this->get(route('search', ['q' => 'deterministic search']))->getContent());

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);
    }

    public function test_search_never_returns_draft_articles(): void
    {
        $draft = $this->publishedArticle([
            'title' => 'A hidden draft about volcanoes',
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);

        $response = $this->get(route('search', ['q' => 'volcanoes']));

        $response->assertOk();
        $response->assertDontSee($draft->title);
    }

    public function test_search_never_returns_review_articles(): void
    {
        $review = $this->publishedArticle([
            'title' => 'A hidden review article about glaciers',
            'status' => ArticleStatus::Review,
            'published_at' => null,
        ]);

        $this->get(route('search', ['q' => 'glaciers']))->assertDontSee($review->title);
    }

    public function test_search_never_returns_scheduled_articles(): void
    {
        $scheduled = $this->publishedArticle([
            'title' => 'A hidden scheduled article about tundras',
            'status' => ArticleStatus::Scheduled,
            'published_at' => null,
        ]);

        $this->get(route('search', ['q' => 'tundras']))->assertDontSee($scheduled->title);
    }

    public function test_search_never_returns_archived_articles(): void
    {
        $archived = $this->publishedArticle([
            'title' => 'A hidden archived article about deserts',
            'status' => ArticleStatus::Archived,
        ]);

        $this->get(route('search', ['q' => 'deserts']))->assertDontSee($archived->title);
    }

    public function test_search_never_returns_future_published_articles(): void
    {
        $future = $this->publishedArticle([
            'title' => 'A hidden future article about comets',
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('search', ['q' => 'comets']))->assertDontSee($future->title);
    }

    public function test_search_result_pages_are_never_indexable(): void
    {
        $this->publishedArticle(['title' => 'A findable article']);

        $response = $this->get(route('search', ['q' => 'findable']));

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
        $response->assertDontSee('rel="canonical"', false);
    }

    public function test_the_empty_search_state_is_also_never_indexable(): void
    {
        $response = $this->get(route('search'));

        $response->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_search_results_do_not_leak_internal_editorial_metadata(): void
    {
        $this->publishedArticle([
            'title' => 'A leak check article',
            'editorial_metadata' => [
                'generation_method' => 'ai_assisted',
                'quality' => ['passed' => true, 'issues' => []],
                'human_edited_by' => 'Jamie Editor',
            ],
        ]);

        $response = $this->get(route('search', ['q' => 'leak check']));

        $response->assertOk();
        $response->assertDontSee('generation_method', false);
        $response->assertDontSee('ai_assisted', false);
        $response->assertDontSee('Jamie Editor', false);
        $response->assertDontSee('editorial_metadata', false);
    }
}
