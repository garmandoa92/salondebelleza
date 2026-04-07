<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Service warranty fields
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('iva_rate');
            $table->integer('warranty_days')->nullable()->after('has_warranty');
            $table->text('warranty_description')->nullable()->after('warranty_days');
        });

        // Appointment warranty fields
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('is_warranty')->default(false)->after('payment_status');
            $table->foreignUuid('warranty_id')->nullable()->after('is_warranty');
        });

        // Warranty tracking table
        Schema::create('appointment_warranties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // active, used, expired, void
            $table->foreignUuid('warranty_appointment_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('voided_by')->nullable();
            $table->text('voided_reason')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_warranties');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['is_warranty', 'warranty_id']);
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_days', 'warranty_description']);
        });
    }
};
