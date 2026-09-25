<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('feed_type')->default('rss');
            $table->text('url');
            $table->string('language', 10)->default('en');
            $table->string('region', 100)->nullable();
            $table->unsignedInteger('poll_interval_minutes')->default(30);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'feed_type']);
            $table->index('last_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_feeds');
    }
};