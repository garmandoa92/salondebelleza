<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 8, 2);
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->integer('max_stylists')->default(-1);
            $table->integer('max_branches')->default(-1);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
