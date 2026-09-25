<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            $table->string('job_type');
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();

            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'job_type']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_jobs');
    }
};