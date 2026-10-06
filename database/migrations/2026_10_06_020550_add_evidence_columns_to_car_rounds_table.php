<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Step 11: implementation evidence belongs to the round it proves, so a re-implementation after
     * "not accepted" adds new evidence instead of replacing the old. The files themselves are
     * attachments on the round.
     */
    public function up(): void
    {
        Schema::table('car_rounds', function (Blueprint $table) {
            $table->text('evidence_notes')->nullable()->after('due_on');
            $table->string('evidence_responsible')->nullable()->after('evidence_notes');
            $table->foreignId('evidence_uploaded_by')->nullable()->after('evidence_responsible')->constrained('users')->nullOnDelete();
            $table->timestamp('evidence_uploaded_at')->nullable()->after('evidence_uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_rounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evidence_uploaded_by');
            $table->dropColumn(['evidence_notes', 'evidence_responsible', 'evidence_uploaded_at']);
        });
    }
};
