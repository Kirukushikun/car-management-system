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
     * Category and sub-category become pairs: a CAR can name several categories (each once), each
     * with one or more sub-categories. Only the sub-categories are stored — each belongs to exactly
     * one category, so the pairs follow from them. Existing CARs keep their one pair.
     */
    public function up(): void
    {
        Schema::create('car_subcategory', function (Blueprint $table) {
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained()->restrictOnDelete();
            $table->primary(['car_id', 'subcategory_id']);
        });

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car): void {
            DB::table('car_subcategory')->insert(['car_id' => $car->id, 'subcategory_id' => $car->subcategory_id]);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('subcategory_id');
        });
    }

    /**
     * Reverse the migrations. Each CAR keeps only its first sub-category and that one's category.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->after('category_id')->constrained()->restrictOnDelete();
        });

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car): void {
            $subcategoryId = DB::table('car_subcategory')->where('car_id', $car->id)->min('subcategory_id');

            DB::table('cars')->where('id', $car->id)->update([
                'subcategory_id' => $subcategoryId,
                'category_id' => DB::table('subcategories')->where('id', $subcategoryId)->value('category_id'),
            ]);
        });

        Schema::dropIfExists('car_subcategory');
    }
};
