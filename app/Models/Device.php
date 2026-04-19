<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Sensor;

class Device extends Model
{
    protected $fillable = [
        'name',
        'location',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }
}