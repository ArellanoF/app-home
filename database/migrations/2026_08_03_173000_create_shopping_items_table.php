<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('quantity', 50)->nullable();
            $table->string('category', 30)->default('other');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->index(['purchased_at', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_items');
    }
};
