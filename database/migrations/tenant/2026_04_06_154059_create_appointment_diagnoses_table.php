<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('hair_condition')->nullable();
            $table->string('skin_condition')->nullable();
            $table->json('products_used')->nullable();
            $table->string('technique')->nullable();
            $table->string('temperature')->nullable();
            $table->string('exposure_time')->nullable();
            $table->text('result')->nullable();
            $table->text('next_visit_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_diagnoses');
    }
};
