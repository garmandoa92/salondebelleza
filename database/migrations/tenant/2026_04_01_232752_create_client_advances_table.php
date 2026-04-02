<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_advances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // advance, payment, refund
            $table->decimal('amount', 10, 2);
            $table->string('payment_method'); // cash, transfer, card_debit, card_credit, other
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('received_by')->constrained('users');
            $table->foreignUuid('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending, applied, refunded
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('appointment_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->after('total_spent');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
        Schema::dropIfExists('client_advances');
    }
};
