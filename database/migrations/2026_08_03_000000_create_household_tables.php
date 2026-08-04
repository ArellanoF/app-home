<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('icon', 20)->default('home');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('recurrence', 20)->default('none');
            $table->foreignId('recurrence_source_id')->nullable()->unique()
                ->constrained('tasks')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['house_id', 'due_date', 'completed_at']);
        });

        Schema::create('shopping_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('quantity', 50)->nullable();
            $table->string('category', 30)->default('other');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
            $table->index(['house_id', 'purchased_at', 'category']);
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->date('meal_date');
            $table->string('meal_type', 20);
            $table->string('name', 150);
            $table->text('notes')->nullable();
            $table->json('ingredients')->nullable();
            $table->timestamps();
            $table->index(['house_id', 'meal_date', 'meal_type']);
        });

        Schema::create('family_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content', 280);
            $table->timestamps();
            $table->index(['house_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_notes');
        Schema::dropIfExists('meals');
        Schema::dropIfExists('shopping_items');
        Schema::dropIfExists('tasks');
    }
};
