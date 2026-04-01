<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('ruc', 13)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->uuid('plan_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->json('data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
