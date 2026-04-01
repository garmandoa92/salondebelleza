<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->string('type');
            $table->uuid('reference_id');
            $table->string('name');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('iva_rate', 5, 2)->default(15);
            $table->decimal('iva_amount', 10, 2)->default(0);
            $table->uuid('stylist_id')->nullable();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('stylist_id')->references('id')->on('stylists')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
