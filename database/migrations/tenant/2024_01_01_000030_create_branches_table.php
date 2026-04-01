<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->uuid('manager_user_id')->nullable();
            $table->json('schedule')->nullable();
            $table->json('settings')->nullable();
            $table->string('sri_establishment', 3)->default('001');
            $table->string('sri_emission_point', 3)->default('001');
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('manager_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Pivot: branch_stylist
        Schema::create('branch_stylist', function (Blueprint $table) {
            $table->uuid('branch_id');
            $table->uuid('stylist_id');
            $table->json('schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->primary(['branch_id', 'stylist_id']);
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('stylist_id')->references('id')->on('stylists')->onDelete('cascade');
        });

        // Add branch_id to existing tables
        Schema::table('appointments', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->after('id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->after('id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::table('blocked_times', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->after('id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('scope')->default('tenant')->after('sort_order');
            $table->uuid('branch_id')->nullable()->after('scope');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        // Stock transfers table
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_branch_id');
            $table->uuid('to_branch_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 10, 3);
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('from_branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('to_branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['scope', 'branch_id']);
        });
        Schema::table('blocked_times', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
        Schema::dropIfExists('branch_stylist');
        Schema::dropIfExists('branches');
    }
};
