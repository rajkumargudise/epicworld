<?php

namespace Tests\Feature\Public;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_subscribe_once_and_the_home_page_offers_the_form(): void
    {
        $this->get('/')->assertOk()->assertSee('Never miss a');

        $this->from('/')->post('/subscribe', ['email' => 'Reader@Example.com'])->assertRedirectContains('#subscribe');
        $this->from('/')->post('/subscribe', ['email' => 'reader@example.com'])->assertRedirectContains('#subscribe');

        $this->assertSame(1, Subscriber::count());
        $this->assertSame('reader@example.com', Subscriber::first()->email);
    }

    public function test_invalid_emails_and_bots_are_not_stored(): void
    {
        $this->from('/')->post('/subscribe', ['email' => 'nope'])->assertSessionHasErrors('email');
        $this->from('/')->post('/subscribe', ['email' => 'bot@example.com', 'website' => 'spam'])->assertRedirect();

        $this->assertSame(0, Subscriber::count());
    }
}
