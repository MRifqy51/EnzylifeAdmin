<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id('id_batch'); // Primary Key sesuai ERD
            $table->unsignedBigInteger('id_devices'); // Foreign Key ke tabel devices
            $table->string('nama_batch'); //[cite: 3]
            $table->date('tanggal_mulai'); //[cite: 3]
            $table->decimal('kapasitas_wadah', 8, 2); //[cite: 3]
            $table->decimal('target_organik', 8, 2); //[cite: 3]
            $table->decimal('target_molase', 8, 2); //[cite: 3]
            $table->decimal('target_air', 8, 2); //[cite: 3]
            $table->string('status')->default('Active'); //[cite: 3]
            $table->timestamps(); // created_at & updated_at[cite: 3]

            // Jika tabel devices sudah ada, tambahkan relasi foreign key:
            // $table->foreign('id_devices')->references('id_devices')->on('devices')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};