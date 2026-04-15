<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
    protected $fillable = [
        'ph',
        'temperature',
        'gas',
        'humidity',
        'status',
    ];
}
