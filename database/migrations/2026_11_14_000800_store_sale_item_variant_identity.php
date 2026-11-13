<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', fn (Blueprint $table) => $table->foreignId('product_fitment_id')->nullable()->constrained('product_fitments')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_fitment_id');
        });
    }
};
