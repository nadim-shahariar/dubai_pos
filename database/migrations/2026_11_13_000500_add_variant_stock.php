<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_fitments', function (Blueprint $table) {
            $table->decimal('stock_qty', 14, 3)->nullable()->after('cost_price');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_fitment_id')->nullable()->after('product_id')
                ->constrained('product_fitments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_fitment_id');
        });

        Schema::table('product_fitments', function (Blueprint $table) {
            $table->dropColumn('stock_qty');
        });
    }
};
