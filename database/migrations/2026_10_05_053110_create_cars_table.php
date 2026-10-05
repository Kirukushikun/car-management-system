<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase I details plus the workflow state. Response/implementation days and due dates are
     * snapshotted from the matrix when the CAR is issued, so later matrix edits never move them.
     */
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('status')->index();
            $table->unsignedSmallInteger('current_round')->default(1);

            $table->foreignId('requestor_id')->constrained('users')->restrictOnDelete();
            $table->string('issued_by');
            $table->string('complainant');
            $table->string('complaint_type');
            $table->text('problem_details');

            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_to_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->constrained()->restrictOnDelete();

            $table->date('complaint_received_on')->nullable();
            $table->date('issued_on')->index();
            $table->unsignedSmallInteger('response_days');
            $table->unsignedSmallInteger('implementation_days');
            $table->date('response_due_on');
            $table->date('implementation_due_on');
            $table->date('revised_due_on')->nullable();

            $table->timestamp('released_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['farm_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
