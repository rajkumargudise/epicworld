<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An editor-configured flag marking a Category or Topic as
     * sensitive (politics, elections, finance/investing,
     * health/medical, legal, disasters/casualties,
     * allegations/accusations, or any other subject an editor
     * chooses to flag). This is the only signal
     * SensitiveContentRouter uses - never inferred from a Story's
     * text or an AI response.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('is_active');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_sensitive');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('is_sensitive');
        });
    }
};
