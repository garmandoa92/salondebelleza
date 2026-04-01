<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('appointment_id')->nullable();
            $table->uuid('client_id')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->string('discount_reason')->nullable();
            $table->decimal('iva_rate', 5, 2)->default(15);
            $table->decimal('iva_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('tip', 10, 2)->default(0);
            $table->uuid('tip_stylist_id')->nullable();
            $table->json('payment_methods')->nullable();
            $table->string('status')->default('draft');
            $table->uuid('sri_invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('tip_stylist_id')->references('id')->on('stylists')->onDelete('set null');
            $table->foreign('sri_invoice_id')->references('id')->on('sri_invoices')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
