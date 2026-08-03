<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->date('meal_date');
            $table->string('meal_type', 20);
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['meal_date', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
