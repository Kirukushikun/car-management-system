<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables the IT Admin maintains. A value in use is deactivated rather than deleted: it stops
     * being offered on new CARs, while CARs that already carry it keep showing it.
     *
     * @var list<string>
     */
    private const TABLES = ['farms', 'issued_to_units', 'categories', 'subcategories'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
