<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('service_category_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 8, 2);
            $table->integer('duration_minutes');
            $table->integer('preparation_minutes')->default(0);
            $table->json('recipe')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('requires_consultation')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_category_id')->references('id')->on('service_categories')->onDelete('cascade');
        });

        // Pivot table: service_stylist
        Schema::create('service_stylist', function (Blueprint $table) {
            $table->uuid('service_id');
            $table->uuid('stylist_id');
            $table->decimal('custom_price', 8, 2)->nullable();
            $table->primary(['service_id', 'stylist_id']);
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('stylist_id')->references('id')->on('stylists')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_stylist');
        Schema::dropIfExists('services');
    }
};
