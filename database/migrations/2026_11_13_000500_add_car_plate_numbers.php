<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->string('car_plate_number', 50)->nullable());
        Schema::table('sales', fn (Blueprint $table) => $table->string('car_plate_number', 50)->nullable());
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('car_plate_number'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('car_plate_number'));
    }
};
