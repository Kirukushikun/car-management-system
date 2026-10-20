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
     * "Issued to" becomes a list: a CAR can be issued to several operational units (all from the
     * business line of its category). Existing CARs keep their one unit as a one-item list.
     */
    public function up(): void
    {
        Schema::create('car_issued_to_unit', function (Blueprint $table) {
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_to_unit_id')->constrained()->restrictOnDelete();
            $table->primary(['car_id', 'issued_to_unit_id']);
        });

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car): void {
            DB::table('car_issued_to_unit')->insert(['car_id' => $car->id, 'issued_to_unit_id' => $car->issued_to_unit_id]);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_to_unit_id');
        });
    }

    /**
     * Reverse the migrations. Each CAR keeps only its first unit.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('issued_to_unit_id')->nullable()->after('farm_id')->constrained()->restrictOnDelete();
        });

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car): void {
            DB::table('cars')->where('id', $car->id)->update([
                'issued_to_unit_id' => DB::table('car_issued_to_unit')->where('car_id', $car->id)->min('issued_to_unit_id'),
            ]);
        });

        Schema::dropIfExists('car_issued_to_unit');
    }
};
