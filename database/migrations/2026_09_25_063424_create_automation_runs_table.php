<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_type');
            $table->string('status')->default('running');

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('items_discovered')->default(0);
            $table->unsignedInteger('items_processed')->default(0);
            $table->unsignedInteger('items_published')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);

            $table->text('error')->nullable();
            $table->json('metrics')->nullable();

            $table->timestamps();

            $table->index(['run_type', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};