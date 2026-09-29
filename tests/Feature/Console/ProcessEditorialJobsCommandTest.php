<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ProcessEditorialJobs;
use App\Enums\EditorialJobStatus;
use App\Enums\StoryStatus;
use App\Models\AutomationRun;
use App\Models\EditorialJob;
use App\Models\Source;
use App\Models\Story;
use App\Services\Ai\AiResult;
use App\Services\Ai\Providers\FakeAiProvider;
use App\Services\Editorial\EditorialJobCreator;
use App\Services\Editorial\FactExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The command-level lock (ProcessEditorialJobs::LOCK_KEY) is the
 * guarantee that holds regardless of how the command is invoked - the
 * scheduler entry's own withoutOverlapping() (EditorialScheduleTest)
 * is a second, scheduler-level layer of the same protection. Mirrors
 * DiscoverStoriesCommandTest exactly.
 */
class ProcessEditorialJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.default' => 'fake']);
    }

    public function test_a_normal_invocation_processes_pending_jobs_and_creates_one_automation_run(): void
    {
        $this->pendingJobWithEvidence();
        app(FakeAiProvider::class)->push(AiResult::success('fake', null, [
            'title' => 'A generated title',
            'body' => str_repeat('Generated body content. ', 3),
            'citations' => ['Reported headline'],
        ]));

        Artisan::call('editorial:process');

        $this->assertSame(1, AutomationRun::count());
        $this->assertSame('editorial_processing', AutomationRun::first()->run_type);
    }

    public function test_an_overlapping_invocation_is_skipped_while_the_lock_is_held(): void
    {
        $lock = Cache::lock(ProcessEditorialJobs::LOCK_KEY, 1800);
        $this->assertTrue($lock->get());

        try {
            $exitCode = Artisan::call('editorial:process');

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('already running', Artisan::output());
            $this->assertSame(0, AutomationRun::count());
        } finally {
            $lock->release();
        }
    }

    public function test_the_lock_is_released_after_a_run_so_a_later_invocation_can_proceed(): void
    {
        Artisan::call('editorial:process');
        Artisan::call('editorial:process');

        // Two full invocations, each releasing its lock before the
        // next one starts, produce two AutomationRuns - not zero
        // (which would mean the lock never released).
        $this->assertSame(2, AutomationRun::count());
    }

    public function test_the_limit_option_bounds_how_many_pending_jobs_are_processed(): void
    {
        $this->pendingJobWithEvidence();
        $this->pendingJobWithEvidence();
        $this->pendingJobWithEvidence();

        Artisan::call('editorial:process', ['--limit' => 2]);

        $run = AutomationRun::first();
        $this->assertSame(2, $run->items_processed);
        $this->assertSame(1, EditorialJob::where('status', EditorialJobStatus::Pending)->count());
    }

    private function pendingJobWithEvidence(): EditorialJob
    {
        $story = Story::create([
            'title' => 'A story',
            'slug' => 'a-story-'.uniqid(),
            'content_hash' => hash('sha256', 'a-story-'.uniqid()),
            'status' => StoryStatus::Candidate,
        ]);
        $source = Source::create(['name' => 'Example Source', 'slug' => 'example-source-'.uniqid()]);
        $source->stories()->attach($story, [
            'source_url' => 'https://example.com/'.uniqid(),
            'title' => 'Reported headline',
            'summary' => 'Reported headline summary',
            'discovered_at' => now(),
        ]);
        app(FactExtractor::class)->extract($story);

        return EditorialJob::create([
            'story_id' => $story->id,
            'job_type' => EditorialJobCreator::JOB_TYPE_ARTICLE_GENERATION,
            'status' => EditorialJobStatus::Pending,
        ]);
    }
}
