<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // before, after, reference, other
            $table->string('photo_path');
            $table->string('thumbnail_path')->nullable();
            $table->text('caption')->nullable();
            $table->foreignUuid('taken_by')->constrained('users');
            $table->boolean('is_visible_to_client')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('appointment_id');
            $table->index(['client_id', 'type']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
        Schema::dropIfExists('appointment_photos');
    }
};
