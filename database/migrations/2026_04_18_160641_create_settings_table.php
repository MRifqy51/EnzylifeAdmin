<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->float('ph_min')->default(4);
            $table->float('ph_max')->default(6.5);

            $table->float('temperature_min')->default(20);
            $table->float('temperature_max')->default(35);

            $table->float('gas_min')->default(0);
            $table->float('gas_max')->default(500);

            $table->float('humidity_min')->default(40);
            $table->float('humidity_max')->default(85);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};