<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The smallest model that lets the CMS distinguish "an ordinary
     * authenticated user" from "someone who may act on editorial
     * content" - a single nullable role column, not a permissions
     * table or a roles/abilities framework. Null means no CMS access
     * at all; 'editor' can view and edit content and move it through
     * review/approval; 'admin' additionally may publish and clear
     * sensitive-content review (see ArticlePolicy).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
