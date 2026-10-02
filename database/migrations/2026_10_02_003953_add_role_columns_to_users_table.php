<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('requestor')->after('email');
            $table->string('scope')->nullable()->after('role');
            $table->foreignId('approver_id')->nullable()->after('scope')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('approver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn(['role', 'scope', 'is_active']);
        });
    }
};
