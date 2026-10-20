<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Type of complaint" becomes a list (stored as JSON). Existing CARs keep their one type.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->json('complaint_types')->nullable()->after('complaint_type');
        });

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car): void {
            DB::table('cars')->where('id', $car->id)->update(['complaint_types' => json_encode([$car->complaint_type])]);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('complaint_type');
        });
    }

    /**
     * Reverse the migrations. Each CAR keeps only its first type.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('complaint_type')->nullable()->after('complainant');
        });

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car): void {
            DB::table('cars')->where('id', $car->id)->update([
                'complaint_type' => json_decode((string) $car->complaint_types, true)[0] ?? 'product',
            ]);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('complaint_types');
        });
    }
};
