<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 0 stored a free-text "scope" (e.g. "PFC", "Sales"). Only Responder roles need a farm,
     * so users now link to farms and the other roles carry no scope at all.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('farm_id')->nullable()->after('role')->constrained()->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('scope')->nullable()->after('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_id');
        });
    }
};
