<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan status analisis per parameter.
     */
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->string('ph_status')
                ->default('not_analyzed');

            $table->string('temp_status')
                ->default('not_analyzed');
        });
    }

    /**
     * Mengembalikan struktur tabel sebelumnya.
     */
    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropColumn([
                'ph_status',
                'temp_status',
            ]);
        });
    }
};
