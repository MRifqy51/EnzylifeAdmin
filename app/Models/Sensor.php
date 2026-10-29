<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Device;
use App\Models\Alert;

class Sensor extends Model
{
    protected $fillable = [
        'device_id',
        'ph',
        'temperature',
        'liquid_temperature',
        'gas',
        'humidity',

        // Data mentah sensor
        'ph_raw',
        'temp_raw',

        // Hasil perhitungan Z-Score
        'z_score_ph',
        'z_score_temp',

        // Status deteksi anomali
        'is_anomaly',
        'anomaly_status',
        
          // Status Z-Score setiap parameter
        'ph_status',
        'temp_status',
    ];

    protected $casts = [
        'is_anomaly' => 'boolean',
        'ph_raw' => 'float',
        'temp_raw' => 'float',
        'z_score_ph' => 'float',
        'z_score_temp' => 'float',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
