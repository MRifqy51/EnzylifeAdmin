<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Sensor;

class AlertService
{
    public static function check(Sensor $sensor): void
    {
        if ($sensor->ph < 3) {
            self::createAlert($sensor, 'pH terlalu rendah. Fermentasi terlalu asam.', 'danger');
        }

        if ($sensor->ph > 5) {
            self::createAlert($sensor, 'pH terlalu tinggi. Fermentasi belum optimal.', 'warning');
        }

        if ($sensor->temperature > 35) {
            self::createAlert($sensor, 'Suhu fermentasi terlalu tinggi.', 'danger');
        }

        if ($sensor->gas > 700) {
            self::createAlert($sensor, 'Gas fermentasi meningkat. Periksa tekanan wadah.', 'warning');
        }

        if ($sensor->humidity < 40) {
            self::createAlert($sensor, 'Kelembaban terlalu rendah.', 'warning');
        }
    }

    private static function createAlert(Sensor $sensor, string $message, string $level): void
    {
        Alert::create([
            'sensor_id' => $sensor->id,
            'message' => $message,
            'level' => $level,
        ]);
    }
}