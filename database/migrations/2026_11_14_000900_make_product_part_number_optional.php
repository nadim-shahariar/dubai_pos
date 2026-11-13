<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('primary_part_number', 50)->nullable()->change());
    }

    public function down(): void
    {
        // Existing products may have no part number. Keep their data when rolling back.
    }
};
