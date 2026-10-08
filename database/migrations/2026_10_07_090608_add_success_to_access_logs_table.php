<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether a sign-in attempt succeeded (organization standard). Null for events that are not
     * sign-in attempts — sign-outs, downloads, prints.
     */
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->boolean('success')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn('success');
        });
    }
};
