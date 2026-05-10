<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\AlertService;
use App\Services\AlertServices;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
        'device_id' => 'required|integer',
        'ph' => 'required|numeric',
        'temperature' => 'required|numeric',
        'gas' => 'required|numeric',
        'humidity' => 'required|numeric',
    ]);

        $sensor = Sensor::create($data);

        AlertService::check($sensor);

        return response()->json([
            'success' => true,
            'message' => 'Sensor data saved',
            'data' => $sensor,
        ]);
    }
}