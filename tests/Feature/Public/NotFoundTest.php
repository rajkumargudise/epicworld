<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unmatched_route_returns_404(): void
    {
        $this->get('/this-route-does-not-exist')->assertNotFound();
    }

    public function test_the_homepage_renders_without_error_when_nothing_has_been_published(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_the_latest_feed_renders_without_error_when_nothing_has_been_published(): void
    {
        $this->get(route('latest'))->assertOk();
    }
}
