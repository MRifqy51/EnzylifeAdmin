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
        'gas',
        'humidity',
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