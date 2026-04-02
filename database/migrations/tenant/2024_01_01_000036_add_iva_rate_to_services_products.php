<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('iva_rate', 5, 2)->nullable()->after('base_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('iva_rate', 5, 2)->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('iva_rate');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('iva_rate');
        });
    }
};
