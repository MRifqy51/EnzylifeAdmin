<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'ph_min',
        'ph_max',
        'temperature_min',
        'temperature_max',
        'gas_min',
        'gas_max',
        'humidity_min',
        'humidity_max',
    ];
}
