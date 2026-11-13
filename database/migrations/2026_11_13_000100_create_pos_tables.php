<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40)->default('general');
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        Schema::create('car_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_brand_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->unique(['car_brand_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('primary_part_number', 50)->unique();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand', 80)->nullable();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('unit', 20)->default('piece');
            $table->decimal('tax_rate', 5, 2)->default(5.00);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->string('secret_cost_code', 20)->unique();
            $table->boolean('track_stock')->default(true);
            $table->decimal('stock_qty', 14, 3)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->string('bin_location', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('cost_pending')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });

        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to');
            $table->string('engine', 50)->nullable();
            $table->string('mapped_part_number', 50)->nullable();
            $table->timestamps();
            $table->index(['car_model_id', 'year_from', 'year_to']);
            $table->index('mapped_part_number');
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type', 20)->default('oem');
            $table->string('reference_number', 50);
            $table->string('brand', 80)->nullable();
            $table->timestamps();
            $table->index('reference_number');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('company_name', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('trn', 15)->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 30);
            $table->string('direction', 10);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('period', 10);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['type', 'period']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('completed');
            $table->string('payment_status', 20)->default('paid');
            $table->string('price_mode', 20)->default('inclusive');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('line_discount_total', 12, 2)->default(0);
            $table->decimal('invoice_discount_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->decimal('change_total', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->string('customer_name', 150)->default('Walk-in Customer');
            $table->string('customer_trn', 15)->nullable();
            $table->string('vehicle', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->index('sold_at');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('primary_part_number', 50);
            $table->string('name', 150);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('original_unit_price', 12, 2)->nullable();
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(5);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->boolean('price_override')->default(false);
            $table->string('override_reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('pos_holds', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->json('cart_json');
            $table->string('note', 255)->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 40)->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('reason', 255);
            $table->string('refund_method', 20)->default('cash');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20)->default('completed');
            $table->timestamp('returned_at');
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->boolean('restock')->default(true);
            $table->string('condition', 30)->default('resellable');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('qty_in', 14, 3)->default(0);
            $table->decimal('qty_out', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('balance_after', 14, 3);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('pos_holds');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_aliases');
        Schema::dropIfExists('product_fitments');
        Schema::dropIfExists('products');
        Schema::dropIfExists('car_models');
        Schema::dropIfExists('car_brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('settings');
    }
};
