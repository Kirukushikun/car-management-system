<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The types that were fixed in code, by their stored value.
     *
     * @var array<string, string>
     */
    private const TYPES = [
        'product' => 'Product Nonconformity',
        'service' => 'Service Nonconformity',
        'other' => 'Others',
    ];

    /**
     * Run the migrations.
     *
     * "Type of complaint" becomes a list the IT Admin maintains: a complaint_types table, and a
     * pivot in place of the JSON column on cars. Existing CARs keep their types.
     */
    public function up(): void
    {
        Schema::create('complaint_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('car_complaint_type', function (Blueprint $table) {
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_type_id')->constrained()->restrictOnDelete();
            $table->primary(['car_id', 'complaint_type_id']);
        });

        $ids = [];

        foreach (self::TYPES as $value => $name) {
            $ids[$value] = DB::table('complaint_types')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car) use ($ids): void {
            $rows = collect(json_decode((string) $car->complaint_types, true) ?: [])
                ->map(fn (string $value): ?int => $ids[$value] ?? null)
                ->filter()->unique()
                ->map(fn (int $typeId): array => ['car_id' => $car->id, 'complaint_type_id' => $typeId])
                ->values()->all();

            DB::table('car_complaint_type')->insert($rows);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('complaint_types');
        });
    }

    /**
     * Reverse the migrations. Types added since map to "other".
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->json('complaint_types')->nullable()->after('complainant');
        });

        $values = DB::table('complaint_types')->pluck('name', 'id')
            ->map(fn (string $name): string => array_search($name, self::TYPES, true) ?: 'other');

        DB::table('cars')->orderBy('id')->lazyById()->each(function (object $car) use ($values): void {
            $types = DB::table('car_complaint_type')->where('car_id', $car->id)->orderBy('complaint_type_id')->pluck('complaint_type_id')
                ->map(fn (int $typeId): string => $values[$typeId])->unique()->values()->all();

            DB::table('cars')->where('id', $car->id)->update(['complaint_types' => json_encode($types ?: ['product'])]);
        });

        Schema::dropIfExists('car_complaint_type');
        Schema::dropIfExists('complaint_types');
    }
};
