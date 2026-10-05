<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per pass through Phase II/III. Round 1 opens when the CAR is issued; "not effective"
     * and "not accepted" each open the next round instead of overwriting the previous one.
     * Containment, root cause, corrective actions and evidence attach to rounds in Phases 4–5.
     */
    public function up(): void
    {
        Schema::create('car_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('opened_by_action');
            $table->date('due_on')->nullable();
            $table->timestamps();

            $table->unique(['car_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_rounds');
    }
};
