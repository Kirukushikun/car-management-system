<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Optional Phase I details: an initial action the Complainant's side already took before
     * filing (what, when, by whom). Separate from the Concerned Department's Phase II containment.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->text('initial_action')->nullable()->after('problem_details');
            $table->date('initial_action_starts_on')->nullable()->after('initial_action');
            $table->date('initial_action_ends_on')->nullable()->after('initial_action_starts_on');
            $table->string('initial_action_responsible')->nullable()->after('initial_action_ends_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['initial_action', 'initial_action_starts_on', 'initial_action_ends_on', 'initial_action_responsible']);
        });
    }
};
