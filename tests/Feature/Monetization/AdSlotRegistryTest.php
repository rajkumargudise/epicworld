<?php

namespace Tests\Feature\Monetization;

use App\Services\Monetization\AdSlotRegistry;
use Tests\TestCase;

/**
 * Milestone 18: AdSlotRegistry is the single decision point for ad
 * eligibility. These pin its behaviour directly (config in, decision
 * out) - the Blade-rendering consequences of these same decisions
 * are covered separately in AdSlotRenderingTest.
 */
class AdSlotRegistryTest extends TestCase
{
    public function test_monetization_is_disabled_by_default(): void
    {
        $this->assertFalse((new AdSlotRegistry)->isEnabled());
    }

    public function test_no_provider_is_configured_by_default(): void
    {
        $this->assertNull((new AdSlotRegistry)->provider());
    }

    public function test_lazy_loading_is_enabled_by_default(): void
    {
        $this->assertTrue((new AdSlotRegistry)->isLazy());
    }

    public function test_provider_reads_back_whatever_is_configured(): void
    {
        config(['monetization.provider' => 'google-adsense']);

        $this->assertSame('google-adsense', (new AdSlotRegistry)->provider());
    }

    public function test_an_empty_string_provider_is_treated_as_unset(): void
    {
        config(['monetization.provider' => '']);

        $this->assertNull((new AdSlotRegistry)->provider());
    }

    public function test_no_slot_is_eligible_while_monetization_is_globally_disabled(): void
    {
        config(['monetization.enabled' => false]);

        $this->assertFalse((new AdSlotRegistry)->isEligible('site_top', 'home'));
        $this->assertFalse((new AdSlotRegistry)->isEligible('article_top', 'article'));
    }

    public function test_a_configured_slot_is_eligible_for_its_configured_context_once_enabled(): void
    {
        config(['monetization.enabled' => true]);

        $this->assertTrue((new AdSlotRegistry)->isEligible('site_top', 'home'));
        $this->assertTrue((new AdSlotRegistry)->isEligible('article_top', 'article'));
    }

    public function test_a_configured_slot_is_not_eligible_for_a_context_it_was_not_given(): void
    {
        config(['monetization.enabled' => true]);

        // article_top only targets the 'article' context.
        $this->assertFalse((new AdSlotRegistry)->isEligible('article_top', 'home'));
    }

    public function test_an_unknown_slot_name_is_never_eligible(): void
    {
        config(['monetization.enabled' => true]);

        $this->assertFalse((new AdSlotRegistry)->isEligible('does-not-exist', 'home'));
    }

    public function test_a_slot_disabled_at_the_config_level_is_not_eligible_even_when_monetization_is_globally_enabled(): void
    {
        config([
            'monetization.enabled' => true,
            'monetization.slots.site_top.enabled' => false,
        ]);

        $this->assertFalse((new AdSlotRegistry)->isEligible('site_top', 'home'));
    }

    public function test_dimensions_are_returned_for_a_known_slot(): void
    {
        $dimensions = (new AdSlotRegistry)->dimensions('article_top');

        $this->assertSame(['width' => 336, 'height' => 280], $dimensions['desktop']);
        $this->assertSame(['width' => 300, 'height' => 250], $dimensions['mobile']);
    }

    public function test_dimensions_are_empty_for_an_unknown_slot(): void
    {
        $this->assertSame([], (new AdSlotRegistry)->dimensions('does-not-exist'));
    }
}
