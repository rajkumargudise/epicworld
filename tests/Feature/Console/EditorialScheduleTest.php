<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ProcessEditorialJobs;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Proves the scheduler entry point itself (routes/console.php) exists
 * and is wired to the real editorial:process command - not that
 * processing logic works, which EditorialJobProcessorTest already
 * covers. Mirrors DiscoveryScheduleTest exactly.
 */
class EditorialScheduleTest extends TestCase
{
    public function test_editorial_process_is_registered_on_the_schedule(): void
    {
        $events = app(Schedule::class)->events();

        $editorialEvents = collect($events)->filter(
            fn ($event) => str_contains($event->command ?? '', 'editorial:process')
        );

        $this->assertNotEmpty(
            $editorialEvents,
            'Expected the scheduler to have an editorial:process entry registered in routes/console.php.'
        );
    }

    public function test_the_scheduled_editorial_processing_entry_guards_against_overlapping_runs(): void
    {
        $events = app(Schedule::class)->events();

        $editorialEvent = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'editorial:process')
        );

        $this->assertNotNull($editorialEvent);
        $this->assertTrue($editorialEvent->withoutOverlapping);
    }

    public function test_the_schedule_frequency_is_driven_by_configuration(): void
    {
        $events = app(Schedule::class)->events();

        $editorialEvent = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'editorial:process')
        );

        $this->assertNotNull($editorialEvent);
        $frequency = (int) config('editorial.schedule.frequency_minutes', 10);
        $this->assertSame("*/{$frequency} * * * *", $editorialEvent->expression);
    }

    public function test_the_scheduled_entry_passes_the_configured_batch_limit(): void
    {
        $events = app(Schedule::class)->events();

        $editorialEvent = collect($events)->first(
            fn ($event) => str_contains($event->command ?? '', 'editorial:process')
        );

        $this->assertNotNull($editorialEvent);
        $limit = (int) config('editorial.schedule.batch_limit', 5);
        $this->assertStringContainsString("--limit={$limit}", $editorialEvent->command);
    }

    public function test_editorial_process_command_is_registered(): void
    {
        $this->assertTrue(array_key_exists(
            'editorial:process',
            app('Illuminate\Contracts\Console\Kernel')->all()
        ));
        $this->assertInstanceOf(ProcessEditorialJobs::class, app(ProcessEditorialJobs::class));
    }
}
