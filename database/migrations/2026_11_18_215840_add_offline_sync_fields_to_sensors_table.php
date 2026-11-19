<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->string('reading_id', 100)
                ->nullable()
                ->unique();

            $table->timestamp('recorded_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropUnique(['reading_id']);
            $table->dropColumn([
                'reading_id',
                'recorded_at',
            ]);
        });
    }
};