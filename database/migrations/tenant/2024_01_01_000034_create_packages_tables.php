<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->string('type'); // sessions, combo
            $table->json('items'); // [{service_id, service_name, quantity}]
            $table->integer('validity_days')->default(365);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('client_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('package_id');
            $table->uuid('sale_id')->nullable();
            $table->string('package_name');
            $table->decimal('package_price', 8, 2);
            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active, completed, expired
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
        });

        Schema::create('client_package_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_package_id');
            $table->uuid('service_id');
            $table->string('service_name');
            $table->integer('total_quantity')->default(1);
            $table->integer('used_quantity')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->uuid('last_appointment_id')->nullable();
            $table->timestamps();

            $table->foreign('client_package_id')->references('id')->on('client_packages')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });

        // Link appointments to package usage
        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('client_package_item_id')->nullable()->after('branch_id');
            $table->foreign('client_package_item_id')->references('id')->on('client_package_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['client_package_item_id']);
            $table->dropColumn('client_package_item_id');
        });
        Schema::dropIfExists('client_package_items');
        Schema::dropIfExists('client_packages');
        Schema::dropIfExists('packages');
    }
};
