<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->text('description')->nullable();
            $table->string('source_type')->default('publisher');
            $table->string('homepage_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_trusted']);
            $table->index('source_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};