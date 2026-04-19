<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sensor;

class Alert extends Model
{
    protected $fillable = [
        'sensor_id',
        'message',
        'level',
    ];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class);
    }
}