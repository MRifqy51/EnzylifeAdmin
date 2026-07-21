<?php

namespace App\Observers;

use App\Models\Sensor;
use App\Models\Alert;
use App\Services\AlertService;

class SensorObserver
{
    public function created(Sensor $sensor): void
    {
        // 1. Jalankan pengecekan alert & pembersihan
        AlertService::check($sensor);

        // 2. Tentukan status akhir sensor berdasarkan alert yang masih aktif
        $activeAlerts = Alert::whereNull('resolved_at')->get();

        $finalStatus = 'optimal';
        if ($activeAlerts->where('level', 'danger')->isNotEmpty()) {
            $finalStatus = 'danger';
        } elseif ($activeAlerts->where('level', 'warning')->isNotEmpty()) {
            $finalStatus = 'warning';
        }

        // 3. Update status tanpa memicu loop event
        $sensor->updateQuietly(['status' => $finalStatus]);
    }
}