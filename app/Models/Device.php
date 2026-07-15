<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'user_id',
        'sd_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }

    // Pastikan fungsi logika sinkronisasi Anda di bawah tetap ada jika ada
    public function syncLocalDataToCloud()
    {
        if ($this->sd_status === 'DISCONNECTED') {
            $this->update(['sd_status' => 'CONNECTED']);
        }
    }
}