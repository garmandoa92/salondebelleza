<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->unique();
            $table->string('email')->nullable()->index();
            $table->string('cedula', 10)->nullable();
            $table->date('birthday')->nullable();
            $table->text('notes')->nullable();
            $table->text('allergies')->nullable();
            $table->json('tags')->nullable();
            $table->uuid('preferred_stylist_id')->nullable();
            $table->integer('loyalty_points')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->integer('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->string('source')->default('walk_in');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('preferred_stylist_id')->references('id')->on('stylists')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
