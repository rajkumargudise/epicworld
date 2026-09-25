<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('dek')->nullable();
            $table->longText('content')->nullable();
            $table->longText('excerpt')->nullable();

            $table->string('status')->default('draft');
            $table->string('content_type')->default('article');

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();

            $table->string('featured_image')->nullable();
            $table->unsignedInteger('reading_time_minutes')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('updated_content_at')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking')->default(false);
            $table->boolean('allow_indexing')->default(true);

            $table->json('editorial_metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['is_featured', 'published_at']);
            $table->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};