<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Responder's Phase II answer for one round (Steps 7–8): interim containment and root
     * cause. Corrective-action lines (Step 9) hang off it. A new round gets a new response, so
     * earlier answers are kept.
     */
    public function up(): void
    {
        Schema::create('car_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_round_id')->unique()->constrained()->cascadeOnDelete();

            $table->text('containment_actions')->nullable();
            $table->date('containment_starts_on')->nullable();
            $table->date('containment_ends_on')->nullable();
            $table->string('containment_responsible')->nullable();

            $table->text('root_cause')->nullable();
            $table->string('root_cause_responsible')->nullable();

            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_responses');
    }
};
