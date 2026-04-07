<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_session_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('appointment_id');
            $table->uuid('user_id');

            $table->json('body_map')->nullable();
            $table->json('techniques')->nullable();
            $table->json('products_used')->nullable();

            $table->integer('actual_duration_minutes')->nullable();
            $table->string('tension_level')->nullable(); // low, medium, high

            $table->text('observations')->nullable();
            $table->text('next_session_recommendation')->nullable();

            $table->text('client_recommendation')->nullable();
            $table->boolean('send_whatsapp')->default(true);
            $table->boolean('whatsapp_sent')->default(false);
            $table->timestamp('whatsapp_sent_at')->nullable();

            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unique('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_session_notes');
    }
};
