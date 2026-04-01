<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sri_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_type')->default('invoice');
            $table->string('establishment', 3)->default('001');
            $table->string('emission_point', 3)->default('001');
            $table->string('sequential', 9);
            $table->string('access_key', 49)->unique();
            $table->date('issue_date');
            $table->string('environment')->default('test');
            $table->string('buyer_identification_type')->default('final_consumer');
            $table->string('buyer_identification')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->decimal('subtotal_0', 10, 2)->default(0);
            $table->decimal('subtotal_iva', 10, 2)->default(0);
            $table->decimal('iva_rate', 5, 2)->default(15);
            $table->decimal('iva_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->longText('xml_unsigned')->nullable();
            $table->longText('xml_signed')->nullable();
            $table->string('ride_path')->nullable();
            $table->string('sri_status')->default('draft');
            $table->string('sri_authorization_number', 49)->nullable();
            $table->timestamp('sri_authorization_date')->nullable();
            $table->json('sri_response')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index('sri_status');
            $table->index(['establishment', 'emission_point', 'sequential']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sri_invoices');
    }
};
