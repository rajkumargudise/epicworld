<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wire_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->default('article'); // article | video
            $table->string('scope', 10);                    // world | news | local
            $table->string('source', 80);
            $table->text('title');
            $table->text('summary')->nullable();
            $table->text('url');
            $table->string('url_hash', 64)->unique();
            $table->text('image_url')->nullable();
            $table->string('video_id', 20)->nullable();
            $table->dateTime('published_at')->index();
            $table->timestamps();

            $table->index(['scope', 'kind', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wire_items');
    }
};
