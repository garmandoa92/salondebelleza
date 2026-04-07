<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('expense_category_id');
            $table->uuid('branch_id')->nullable();

            // Datos básicos
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->decimal('iva_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->date('expense_date');
            $table->string('payment_method')->default('cash');

            // Comprobante SRI del proveedor
            $table->boolean('is_deductible')->default(false);
            $table->boolean('has_sri_invoice')->default(false);
            $table->string('sri_invoice_number')->nullable();
            $table->string('sri_authorization_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_ruc')->nullable();
            $table->string('receipt_file_path')->nullable();

            // Retención en la fuente
            $table->boolean('has_retention')->default(false);
            $table->decimal('retention_percentage', 5, 2)->default(0);
            $table->decimal('retention_amount', 10, 2)->default(0);

            // Recurrencia
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_type')->nullable(); // monthly, bimonthly, quarterly, annual
            $table->integer('recurrence_day')->nullable();
            $table->uuid('parent_expense_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('expense_category_id')->references('id')->on('expense_categories');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('parent_expense_id')->references('id')->on('expenses');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
