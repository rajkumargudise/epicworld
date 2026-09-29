<?php

namespace Tests\Feature\Console;

use App\Console\Commands\DiscoverStories;
use App\Models\AutomationRun;
use App\Models\Source;
use App\Models\SourceFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The command-level lock (DiscoverStories::LOCK_KEY) is the guarantee
 * that holds regardless of how the command is invoked - the scheduler
 * entry's own withoutOverlapping() (DiscoveryScheduleTest) is a
 * second, scheduler-level layer of the same protection.
 */
class DiscoverStoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_invocation_runs_discovery_and_creates_one_automation_run(): void
    {
        $feed = $this->activeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        Artisan::call('stories:discover');

        $this->assertSame(1, AutomationRun::count());
    }

    public function test_an_overlapping_invocation_is_skipped_while_the_lock_is_held(): void
    {
        Http::preventStrayRequests();
        $lock = Cache::lock(DiscoverStories::LOCK_KEY, 1800);
        $this->assertTrue($lock->get());

        try {
            $exitCode = Artisan::call('stories:discover');

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('already running', Artisan::output());
            $this->assertSame(0, AutomationRun::count());
        } finally {
            $lock->release();
        }
    }

    public function test_the_lock_is_released_after_a_run_so_a_later_invocation_can_proceed(): void
    {
        $feed = $this->activeFeed();
        Http::fake([$feed->url => Http::response($this->rss(), 200)]);

        Artisan::call('stories:discover');
        Artisan::call('stories:discover');

        // Two full invocations, each releasing its lock before the
        // next one starts, produce two AutomationRuns - not zero
        // (which would mean the lock never released) and not one
        // merged run.
        $this->assertSame(2, AutomationRun::count());
    }

    private function activeFeed(): SourceFeed
    {
        $source = Source::create(['name' => 'example-source', 'slug' => 'example-source-'.uniqid()]);

        return SourceFeed::create([
            'source_id' => $source->id,
            'name' => 'Example Feed',
            'url' => 'https://example.com/feed-'.uniqid().'.xml',
        ]);
    }

    private function rss(): string
    {
        return <<<'XML'
<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>Example Feed</title>
        <item>
            <title>Command test story</title>
            <link>https://example.com/stories/command-test</link>
            <guid>command-test-1</guid>
            <pubDate>Fri, 25 Sep 2026 12:00:00 +0000</pubDate>
        </item>
    </channel>
</rss>
XML;
    }
}
