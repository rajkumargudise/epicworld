<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->default('image');
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->string('credit')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};