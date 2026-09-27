<?php

use App\Support\CanonicalUrl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('canonical_url_hash', 64)->nullable()->after('canonical_url');
        });

        DB::table('stories')
            ->whereNotNull('canonical_url')
            ->orderBy('id')
            ->chunkById(200, function ($stories) {
                foreach ($stories as $story) {
                    DB::table('stories')
                        ->where('id', $story->id)
                        ->update([
                            'canonical_url_hash' => CanonicalUrl::hash($story->canonical_url),
                        ]);
                }
            });

        Schema::table('stories', function (Blueprint $table) {
            $table->unique('canonical_url_hash');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropUnique(['canonical_url_hash']);
            $table->dropColumn('canonical_url_hash');
        });
    }
};
