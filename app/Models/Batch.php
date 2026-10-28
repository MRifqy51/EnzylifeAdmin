<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $table = 'batches';
    protected $primaryKey = 'id_batch'; // Menyesuaikan primary key dari ERD[cite: 3]
    
    protected $fillable = [
        'id_devices',
        'nama_batch',
        'tanggal_mulai',
        'kapasitas_wadah',
        'target_organik',
        'target_molase',
        'target_air',
        'status',
    ];

    // Relasi opsional ke Device (jika diperlukan)
    public function device()
    {
        return $this->belongsTo(Device::class, 'id_devices', 'id_devices');
    }
}