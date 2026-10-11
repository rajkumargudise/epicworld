<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wire_items', function (Blueprint $table) {
            // Plain text the feed itself supplied beyond the short summary. Used only as
            // private reference material for the report writer - never shown to readers.
            $table->mediumText('source_text')->nullable()->after('summary');
            // The full EpicWorld report written for this headline.
            $table->longText('report')->nullable()->after('source_text');
            $table->json('report_meta')->nullable()->after('report');
            $table->string('report_status', 12)->nullable()->index()->after('report_meta');
            $table->unsignedTinyInteger('report_attempts')->default(0)->after('report_status');
            $table->string('report_error', 250)->nullable()->after('report_attempts');
            $table->timestamp('report_generated_at')->nullable()->after('report_error');
        });
    }

    public function down(): void
    {
        Schema::table('wire_items', function (Blueprint $table) {
            $table->dropColumn(['source_text', 'report', 'report_meta', 'report_status', 'report_attempts', 'report_error', 'report_generated_at']);
        });
    }
};
