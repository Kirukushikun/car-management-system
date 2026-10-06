<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Step 9 corrective-action lines — the paper form's "Corrective Action / Responsible /
     * Target Date" table. A response may have several. Responsible and dates may be blank while
     * the response is still a draft.
     */
    public function up(): void
    {
        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_response_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->string('responsible')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
    }
};
