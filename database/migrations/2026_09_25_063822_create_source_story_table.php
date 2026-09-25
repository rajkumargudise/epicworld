<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_story', function (Blueprint $table) {
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();

            $table->text('source_url')->nullable();
            $table->string('external_id')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->json('metadata')->nullable();

            $table->primary(['source_id', 'story_id']);

            $table->index('external_id');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_story');
    }
};