<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stylist_id');
            $table->uuid('sale_item_id');
            $table->decimal('amount', 10, 2);
            $table->decimal('rate', 5, 2);
            $table->string('status')->default('pending');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('paid_at')->nullable();
            $table->uuid('paid_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stylist_id')->references('id')->on('stylists')->onDelete('cascade');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->onDelete('cascade');
            $table->foreign('paid_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
