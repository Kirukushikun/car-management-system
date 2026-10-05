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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_line_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('response_days');
            $table->unsignedSmallInteger('implementation_days');
            $table->timestamps();

            $table->unique(['business_line_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
