<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sensor;

class Alert extends Model
{
    protected $fillable = [
        'sensor_id',
        'type',
        'message',
        'level',
        'resolved_at',
    ];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class);
    }
}