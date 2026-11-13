<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('paid_at');
            $table->index(['customer_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
