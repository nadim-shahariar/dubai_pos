<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->uuid('request_key')->unique();
            $table->string('payload_hash', 64);
            $table->date('received_on')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_fitment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('product_name', 150);
            $table->string('variant_label')->nullable();
            $table->string('part_number', 50)->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('balance_after', 14, 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_items');
        Schema::dropIfExists('stock_receipts');
    }
};
