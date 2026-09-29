<?php

namespace Tests\Feature\Console;

use App\Console\Commands\DiscoverStories;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Proves the scheduler entry point itself (routes/console.php) exists
 * and is wired to the real stories:discover command - not that
 * discovery logic works, which DiscoveryRunnerTest already covers.
 */
class DiscoveryScheduleTest extends TestCase
{
    public function test_stories_discover_is_registered_on_the_schedule(): void
    {
        $events = app(Schedule::class)->events();

        $discoveryEvents = collect($events)->filter(
            fn ($event) => str_contains($event->command ?? '', 'stories:discover')
        );

        $this->assertNotEmpty(
            $discoveryEvents,
            'Expected the scheduler to have a stories:discover entry registered in routes/console.php.'
        );
    }

    public function test_the_scheduled_discovery_entry_guards_against_overlapping_runs(): void
    {
        $events = app(Schedule::class)->events();

        $discoveryEvent = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'stories:discover')
        );

        $this->assertNotNull($discoveryEvent);
        $this->assertTrue($discoveryEvent->withoutOverlapping);
    }

    public function test_the_schedule_frequency_is_driven_by_configuration(): void
    {
        $events = app(Schedule::class)->events();

        $discoveryEvent = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'stories:discover')
        );

        $this->assertNotNull($discoveryEvent);
        $frequency = (int) config('discovery.schedule.frequency_minutes', 15);
        $this->assertSame("*/{$frequency} * * * *", $discoveryEvent->expression);
    }

    public function test_discover_stories_command_is_registered(): void
    {
        $this->assertTrue(array_key_exists(
            'stories:discover',
            app('Illuminate\Contracts\Console\Kernel')->all()
        ));
        $this->assertInstanceOf(DiscoverStories::class, app(DiscoverStories::class));
    }
}
