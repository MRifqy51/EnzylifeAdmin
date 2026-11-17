
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->float('ph_raw')->nullable();
            $table->float('temp_raw')->nullable();

            $table->float('z_score_ph')->nullable();
            $table->float('z_score_temp')->nullable();

            $table->boolean('is_anomaly')
                  ->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropColumn([
                'ph_raw',
                'temp_raw',
                'z_score_ph',
                'z_score_temp',
                'is_anomaly',
            ]);
        });
    }
};
