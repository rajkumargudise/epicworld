<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An editor-configured default Topic for a Source (e.g. "TechCrunch"
     * -> Technology). This is the deterministic signal StoryClassifier
     * uses to assign a Story's topic - it is set by whoever configures
     * the source, never inferred by the classifier itself.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->foreignId('default_topic_id')
                ->nullable()
                ->after('is_active')
                ->constrained('topics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_topic_id');
        });
    }
};
