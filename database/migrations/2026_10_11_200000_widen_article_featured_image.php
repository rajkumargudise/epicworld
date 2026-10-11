<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Free-image hosts such as Wikimedia can return addresses longer than 191 characters.
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('featured_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('featured_image')->nullable()->change();
        });
    }
};
