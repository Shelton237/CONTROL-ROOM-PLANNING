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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->enum('type', ['rotation', 'fixed_day']);
            $table->unsignedTinyInteger('offset')->nullable();
            $table->unsignedTinyInteger('binome')->nullable();
            $table->json('day_spec')->nullable();
            $table->unsignedTinyInteger('alt_parity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
