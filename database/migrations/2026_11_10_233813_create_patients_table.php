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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('sv_number', 10)->unique();
            $table->date('date_of_birth');
            $table->string('insurer');
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->date('enrolled_at');
            $table->date('deregistered_at')->nullable();
            $table->string('deregistration_reason')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index(['enrolled_at', 'deregistered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
