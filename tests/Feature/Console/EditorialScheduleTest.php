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
            fn ($event) => ($event->description ?? '') === 'editorial-process'
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
            fn ($event) => ($event->description ?? '') === 'editorial-process'
        );

        $this->assertNotNull($editorialEvent);
        $this->assertTrue($editorialEvent->withoutOverlapping);
    }

    public function test_the_schedule_frequency_is_driven_by_configuration(): void
    {
        $events = app(Schedule::class)->events();

        $editorialEvent = collect($events)->first(
            fn ($event) => ($event->description ?? '') === 'editorial-process'
        );

        $this->assertNotNull($editorialEvent);
        $frequency = (int) config('editorial.schedule.frequency_minutes', 10);
        $this->assertSame("*/{$frequency} * * * *", $editorialEvent->expression);
    }

    public function test_every_scheduled_task_runs_in_process_because_the_host_blocks_proc_open(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertInstanceOf(
                \Illuminate\Console\Scheduling\CallbackEvent::class,
                $event,
                'Schedule::command() spawns a child process via proc_open, which Hostinger disables - use Schedule::call(Artisan::call(...)).'
            );
        }
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
