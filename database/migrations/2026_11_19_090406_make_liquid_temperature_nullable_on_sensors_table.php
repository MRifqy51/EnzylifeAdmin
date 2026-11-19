
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->double('liquid_temperature')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan NOT NULL secara otomatis.
        // Data NULL yang tersimpan setelah migration ini
        // dapat menyebabkan rollback gagal.
    }
};
