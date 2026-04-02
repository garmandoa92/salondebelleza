<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_status')->default('pending')->after('status');
            $table->integer('sessions_used')->nullable()->after('client_package_item_id');
        });

        Schema::table('client_packages', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->unique()->after('id');
        });

        Schema::create('package_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_package_id');
            $table->uuid('client_package_item_id');
            $table->uuid('appointment_id')->nullable();
            $table->uuid('service_id');
            $table->integer('sessions_used')->default(1);
            $table->integer('sessions_before');
            $table->integer('sessions_after');
            $table->uuid('used_by');
            $table->text('notes')->nullable();
            $table->timestamp('created_at');

            $table->foreign('client_package_id')->references('id')->on('client_packages')->onDelete('cascade');
            $table->foreign('client_package_item_id')->references('id')->on('client_package_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_usage_logs');
        Schema::table('client_packages', function (Blueprint $table) {
            $table->dropColumn('receipt_number');
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'sessions_used']);
        });
    }
};
