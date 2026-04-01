<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('type')->default('use');
            $table->string('unit')->default('unit');
            $table->decimal('cost_price', 8, 2)->nullable();
            $table->decimal('sale_price', 8, 2)->nullable();
            $table->decimal('stock', 10, 3)->default(0);
            $table->decimal('min_stock', 10, 3)->default(0);
            $table->string('supplier')->nullable();
            $table->string('brand')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
