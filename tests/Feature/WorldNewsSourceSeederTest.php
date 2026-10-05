<?php

namespace Tests\Feature;

use App\Models\SourceFeed;
use Database\Seeders\EpicWorldSeeder;
use Database\Seeders\WorldNewsSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldNewsSourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_many_active_feeds_and_is_idempotent(): void
    {
        $this->seed(EpicWorldSeeder::class);
        $this->seed(WorldNewsSourceSeeder::class);
        $first = SourceFeed::count();
        $this->seed(WorldNewsSourceSeeder::class);

        $this->assertGreaterThanOrEqual(15, $first);
        $this->assertSame($first, SourceFeed::count());
        $this->assertSame(0, SourceFeed::where('is_active', false)->count());
        $this->assertTrue(SourceFeed::where('url', 'like', 'https://%')->count() === $first, 'every feed must be https');
    }
}
