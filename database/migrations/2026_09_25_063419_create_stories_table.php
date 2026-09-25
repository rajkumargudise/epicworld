<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('content_hash', 64)->nullable()->unique();
            $table->string('status')->default('discovered');
            $table->string('importance')->default('normal');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('facts')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'importance']);
            $table->index('occurred_at');
            $table->index('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};