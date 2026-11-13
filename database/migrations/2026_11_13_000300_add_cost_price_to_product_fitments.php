<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_fitments', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable()->after('mapped_part_number');
        });
    }

    public function down(): void
    {
        Schema::table('product_fitments', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
