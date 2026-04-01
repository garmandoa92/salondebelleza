<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('sri_ambiente')->default('test')->after('sri_emission_point');
            $table->string('sri_regimen')->default('general')->after('sri_ambiente');
            $table->string('sri_obligado_contabilidad', 2)->default('NO')->after('sri_regimen');
            $table->boolean('sri_certificate_uploaded')->default(false)->after('sri_obligado_contabilidad');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['sri_ambiente', 'sri_regimen', 'sri_obligado_contabilidad', 'sri_certificate_uploaded']);
        });
    }
};
