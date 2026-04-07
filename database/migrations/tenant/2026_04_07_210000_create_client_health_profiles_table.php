<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_health_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');

            $table->json('allergies')->nullable();
            $table->text('allergies_notes')->nullable();

            $table->json('medical_conditions')->nullable();
            $table->text('medical_notes')->nullable();

            $table->text('current_medications')->nullable();
            $table->text('contraindications')->nullable();

            $table->json('avoid_zones')->nullable();

            $table->tinyInteger('pressure_preference')->default(2);
            $table->json('personal_preferences')->nullable();

            $table->text('therapist_notes')->nullable();

            $table->timestamp('last_updated_by_client')->nullable();
            $table->uuid('last_updated_by_user_id')->nullable();

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('last_updated_by_user_id')->references('id')->on('users');
            $table->unique('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_health_profiles');
    }
};
