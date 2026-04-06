<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_diagnoses', function (Blueprint $table) {
            $table->renameColumn('hair_condition', 'initial_condition');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_diagnoses', function (Blueprint $table) {
            $table->renameColumn('initial_condition', 'hair_condition');
        });
    }
};
