<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Sensor;
use App\Models\Setting;
use Carbon\Carbon;

class AlertService
{
    public static function check(Sensor $sensor): void
    {
        $setting = Setting::first();
        if (!$setting) {
            return;
        }

        // 1. Cek pH
        self::evaluateParameter(
            $sensor,
            'ph',
            $sensor->ph,
            $setting->ph_min,
            $setting->ph_max,
            "Tingkat pH ({$sensor->ph}) di luar batas aman ({$setting->ph_min} - {$setting->ph_max}).",
            'warning'
        );

        // 2. Cek Suhu Udara
        self::evaluateParameter(
            $sensor,
            'temperature',
            $sensor->temperature,
            $setting->temperature_min,
            $setting->temperature_max,
            "Suhu Udara ({$sensor->temperature}°C) di luar batas aman ({$setting->temperature_min}°C - {$setting->temperature_max}°C).",
            'danger'
        );

        // 3. Cek Suhu Cairan
        self::evaluateParameter(
            $sensor,
            'liquid_temperature',
            $sensor->liquid_temperature,
            $setting->liquid_temperature_min ?? $setting->temperature_min,
            $setting->liquid_temperature_max ?? $setting->temperature_max,
            "Suhu Cairan ({$sensor->liquid_temperature}°C) di luar batas aman.",
            'danger'
        );

        // 4. Cek Gas
        self::evaluateParameter(
            $sensor,
            'gas',
            $sensor->gas,
            $setting->gas_min,
            $setting->gas_max,
            "Konsentrasi Gas ({$sensor->gas} ppm) melebihi batas aman.",
            'warning'
        );

        // 5. Cek Kelembapan
        self::evaluateParameter(
            $sensor,
            'humidity',
            $sensor->humidity,
            $setting->humidity_min,
            $setting->humidity_max,
            "Kelembapan ({$sensor->humidity}%) di luar batas aman ({$setting->humidity_min}% - {$setting->humidity_max}%).",
            'warning'
        );

        // Hapus alert yang sudah normal (resolved) selama 15 detik atau lebih
        self::cleanupResolvedAlerts();
    }

    private static function evaluateParameter(
        Sensor $sensor,
        string $type,
        ?float $val,
        ?float $min,
        ?float $max,
        string $message,
        string $level
    ): void {
        if ($val === null || $min === null || $max === null) {
            return;
        }

        $isAbnormal = ($val < $min || $val > $max);
        $activeAlert = Alert::where('type', $type)->whereNull('resolved_at')->first();

        if ($isAbnormal) {
            if (!$activeAlert) {
                // Buat alert baru jika belum ada alert aktif
                Alert::create([
                    'sensor_id' => $sensor->id,
                    'type'      => $type,
                    'message'   => $message,
                    'level'     => $level,
                ]);
            } else {
                // Update isi pesan dengan data sensor paling baru
                $activeAlert->update([
                    'message'   => $message,
                    'sensor_id' => $sensor->id,
                ]);
            }
        } else {
            // Jika nilai sensor KEMBALI NORMAL
            if ($activeAlert) {
                $activeAlert->update([
                    'resolved_at' => now(),
                ]);
            }
        }
    }

    private static function cleanupResolvedAlerts(): void
    {
        $holdDurationSeconds = 15; // Jeda waktu sebelum benar-benar dihapus permanen

        Alert::whereNotNull('resolved_at')
            ->where('resolved_at', '<=', Carbon::now()->subSeconds($holdDurationSeconds))
            ->delete();
    }
}