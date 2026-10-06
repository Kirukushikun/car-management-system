<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * What was accessed, for events beyond sign-in: e.g. "CAR-2026-0147 · spoiled-eggs.mp4" for an
     * attachment download, or the CAR reference for a print.
     */
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn('subject');
        });
    }
};
