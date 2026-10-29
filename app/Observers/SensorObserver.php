<?php

namespace App\Observers;

use App\Models\Sensor;
use App\Models\Setting;
use App\Models\Alert;
use App\Services\WhatsAppService;

class SensorObserver
{
    /**
     * Dipanggil ketika data sensor baru disimpan.
     */
    public function created(Sensor $sensor): void
    {
        // ==========================================
        // 1. DETEKSI ANOMALI Z-SCORE
        // ==========================================

        $zScoreFields = [
            'ph' => [
                'status' => 'ph_status',
                'z_score' => 'z_score_ph',
                'label' => 'pH',
            ],
            'liquid_temperature' => [
                'status' => 'temp_status',
                'z_score' => 'z_score_temp',
                'label' => 'Suhu Cairan',
            ],
        ];

        foreach ($zScoreFields as $field => $config) {

            $parameterStatus = $sensor->{$config['status']};
            $zScore = $sensor->{$config['z_score']};
            $label = $config['label'];

            // Hanya status anomaly yang dapat
            // menghasilkan alert anomali statistik.
            if ($parameterStatus !== 'anomaly') {
                continue;
            }

            // Pastikan Z-Score tersedia, valid,
            // dan melewati batas |Z| > 3.
            if (
                $zScore === null ||
                !is_finite((float) $zScore) ||
                abs((float) $zScore) <= 3
            ) {
                continue;
            }

            // Cooldown anomali 5 menit per perangkat
            $recentAlertExists = Alert::where(
                'message',
                'LIKE',
                "Anomali Z-Score {$label}:%"
            )
                ->where('level', 'danger')
                ->whereHas('sensor', function ($query) use ($sensor) {
                    $query->where(
                        'device_id',
                        $sensor->device_id
                    );
                })
                ->where(
                    'created_at',
                    '>=',
                    now()->subMinutes(5)
                )
                ->exists();

            if ($recentAlertExists) {
                continue;
            }

            // Simpan alert anomali
            $alert = Alert::create([
                'sensor_id' => $sensor->id,
                'message' =>
                    "Anomali Z-Score {$label}: " .
                    "nilai Z = {$zScore} " .
                    "(batas |Z| > 3).",
                'level' => 'danger',
            ]);

            // Kirim notifikasi WhatsApp
            $this->sendWhatsAppAlert($alert);
        }

        // ==========================================
        // 2. PEMERIKSAAN THRESHOLD
        // ==========================================

        $setting = Setting::first();

        if (!$setting) {
            return;
        }

        // Parameter yang diperiksa
        $fields = [
            'ph' => 'pH Level',
            'liquid_temperature' => 'Liquid Temperature',
            
        ];

        foreach ($fields as $key => $label) {

            // Abaikan nilai sensor kosong
            if ($sensor->$key === null) {
                continue;
            }

            // ======================================
            // 3. VALIDASI STATUS PER PARAMETER
            // ======================================

            if ($key === 'ph') {

                // Hanya pH berstatus normal yang
                // boleh diperiksa terhadap threshold.
                if ($sensor->ph_status !== 'normal') {
                    continue;
                }

            } elseif ($key === 'liquid_temperature') {

                // Hanya suhu cairan berstatus normal
                // yang boleh diperiksa terhadap threshold.
                if ($sensor->temp_status !== 'normal') {
                    continue;
                }
            }

            $value = (float) $sensor->$key;

            // Ambil threshold minimum dan maksimum
            $minKey = "{$key}_min";
            $maxKey = "{$key}_max";

            $minThreshold = $setting->$minKey !== null
                ? (float) $setting->$minKey
                : null;

            $maxThreshold = $setting->$maxKey !== null
                ? (float) $setting->$maxKey
                : null;

            // ======================================
            // 4. ALERT DANGER
            // ======================================

            if (
                $maxThreshold !== null &&
                $value > $maxThreshold
            ) {

                $recentAlertExists = Alert::where(
                    'message',
                    'LIKE',
                    "Sensor {$label}%"
                )
                    ->where('level', 'danger')
                    ->whereHas('sensor', function ($query) use ($sensor) {
                        $query->where(
                            'device_id',
                            $sensor->device_id
                        );
                    })
                    ->where(
                        'created_at',
                        '>=',
                        now()->subMinutes(5)
                    )
                    ->exists();

                if (!$recentAlertExists) {

                    $alert = Alert::create([
                        'sensor_id' => $sensor->id,
                        'message' =>
                            "Sensor {$label} bernilai {$value}, " .
                            "melampaui batas maksimum ({$maxThreshold})!",
                        'level' => 'danger',
                    ]);

                    // Kirim WhatsApp untuk alert baru
                    $this->sendWhatsAppAlert($alert);
                }
            }

            // ======================================
            // 5. ALERT WARNING
            // ======================================

            elseif (
                $minThreshold !== null &&
                $value < $minThreshold
            ) {

                $recentAlertExists = Alert::where(
                    'message',
                    'LIKE',
                    "Sensor {$label}%"
                )
                    ->where('level', 'warning')
                    ->whereHas('sensor', function ($query) use ($sensor) {
                        $query->where(
                            'device_id',
                            $sensor->device_id
                        );
                    })
                    ->where(
                        'created_at',
                        '>=',
                        now()->subMinutes(5)
                    )
                    ->exists();

                if (!$recentAlertExists) {

                    $alert = Alert::create([
                        'sensor_id' => $sensor->id,
                        'message' =>
                            "Sensor {$label} bernilai {$value}, " .
                            "di bawah batas minimum ({$minThreshold})!",
                        'level' => 'warning',
                    ]);

                    // Kirim WhatsApp untuk alert baru
                    $this->sendWhatsAppAlert($alert);
                }
            }
        }
    }

    /**
     * Mengirim notifikasi WhatsApp kepada admin
     * melalui layanan Fonnte.
     */
    private function sendWhatsAppAlert(Alert $alert): void
    {
        // Nomor penerima WhatsApp       
        $phone = Setting::query()->value('whatsapp_number');

        // Gunakan nomor cadangan dari .env jika
        // nomor belum tersedia di database.
        if (blank($phone)) {
            $phone = config('services.fonnte.admin_phone');
        }

        // Jangan mengirim jika nomor belum tersedia
        if (empty($phone)) {
            return;
        }

        // Format pesan peringatan
        $message = "PERINGATAN ENZYLIFE\n\n"
            . "Tingkat: " . strtoupper($alert->level) . "\n"
            . "Pesan: " . $alert->message . "\n"
            . "Waktu: "
            . $alert->created_at->format('d-m-Y H:i:s')
            . "\n\n"
            . "Silakan periksa dashboard EnzyLife.";

        // Kirim melalui WhatsApp Gateway
        app(WhatsAppService::class)->sendMessage(
            $phone,
            $message
        );
    }
}
