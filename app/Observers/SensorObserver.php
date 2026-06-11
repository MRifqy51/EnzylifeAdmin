<?php

namespace App\Observers;

use App\Models\Sensor;
use App\Models\Setting;
use App\Models\Alert;

class SensorObserver
{
    /**
     * Handle the Sensor "created" event.
     */
    public function created(Sensor $sensor): void
    {
        // 1. Ambil batasan threshold saat ini dari tabel settings
        $setting = Setting::first();
        if (!$setting) return;

        // 2. Mapping nama field di database sensor dan label pesan alert
        $fields = [
            'ph' => 'pH Level',
            'temperature' => 'Air Temperature',
            'liquid_temperature' => 'Liquid Temperature',
            'gas' => 'Gas Concentration',
            'humidity' => 'Humidity'
        ];

        $hasDanger = false;
        $hasWarning = false;

        // 3. Looping untuk cek setiap nilai sensor dengan threshold resmi
        foreach ($fields as $key => $label) {
            // Paksa tipe data menjadi float agar akurat saat dibandingkan angka
            $value = (float) $sensor->$key;
            
            $minKey = "{$key}_min";
            $maxKey = "{$key}_max";

            $minThreshold = $setting->$minKey !== null ? (float) $setting->$minKey : null;
            $maxThreshold = $setting->$maxKey !== null ? (float) $setting->$maxKey : null;

            // Kondisi A: Jika nilai di atas batas maksimum (Danger)
            if ($maxThreshold !== null && $value > $maxThreshold) {
                $hasDanger = true;

                // Cegah spam: Cek apakah alert dengan pesan serupa sudah pernah dibuat dalam rentang waktu dekat
                $recentAlertExists = Alert::where('message', 'LIKE', "Sensor {$label}%")
                    ->where('level', 'danger')
                    ->where('created_at', '>=', now()->subMinutes(5)) // tidak double alert dalam 5 menit terakhir
                    ->exists();

                if (!$recentAlertExists) {
                    Alert::create([
                        'sensor_id' => $sensor->id,
                        'message' => "Sensor {$label} bernilai {$value}, melampaui batas maksimum ({$maxThreshold})!",
                        'level' => 'danger',
                    ]);
                }
            } 
            // Kondisi B: Jika nilai di bawah batas minimum (Warning)
            elseif ($minThreshold !== null && $value < $minThreshold) {
                $hasWarning = true;

                $recentAlertExists = Alert::where('message', 'LIKE', "Sensor {$label}%")
                    ->where('level', 'warning')
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->exists();

                if (!$recentAlertExists) {
                    Alert::create([
                        'sensor_id' => $sensor->id,
                        'message' => "Sensor {$label} bernilai {$value}, di bawah batas minimum ({$minThreshold})!",
                        'level' => 'warning',
                    ]);
                }
            }
        }

        // 4. Tentukan status akhir untuk baris sensor ini
        $finalStatus = 'optimal';
        if ($hasDanger) {
            $finalStatus = 'danger';
        } elseif ($hasWarning) {
            $finalStatus = 'warning';
        }

        // 5. Update status kolom record sensor secara diam-diam tanpa memicu loop event created
        $sensor->updateQuietly(['status' => $finalStatus]);
    }
}