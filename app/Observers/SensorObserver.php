<?php

namespace App\Observers;

use App\Models\Alert;
use App\Models\Sensor;
use App\Models\Setting;
use App\Services\WhatsAppService;

class SensorObserver
{
    private const COOLDOWN_MINUTES = 5;

    /**
     * Dipanggil setiap kali data sensor baru dibuat.
     */
    public function created(Sensor $sensor): void
    {
        $this->checkThreshold($sensor);
    }

    /**
     * 1. DETEKSI ANOMALI Z-SCORE
     *
     * Anomali statistik dipisahkan berdasarkan parameter.
     */
    private function checkZScore(Sensor $sensor): void
    {
        $parameters = [
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

        foreach ($parameters as $field => $config) {
            $type = "zscore_{$field}";
            $status = $sensor->{$config['status']};
            $zScore = $sensor->{$config['z_score']};
            $label = $config['label'];

            // Data yang dinyatakan normal menyelesaikan
            // alert anomali sebelumnya.
            if ($status === 'normal') {
                $this->resolveAlert($sensor, $type);
                continue;
            }

            // Status lain, seperti insufficient_data
            // atau invalid, tidak dianggap normal.
            if ($status !== 'anomaly') {
                continue;
            }

            // Pastikan nilai Z-Score tersedia dan valid.
            if (
                $zScore === null ||
                !is_numeric($zScore) ||
                !is_finite((float) $zScore) ||
                abs((float) $zScore) <= 3.0
            ) {
                continue;
            }

            $message = "Anomali Z-Score {$label}: "
                . "nilai Z = {$zScore} "
                . "(batas |Z| > 3).";

            $this->handleAlert(
                $sensor,
                $type,
                'danger',
                $message
            );
        }
    }

    /**
     * 2. PEMERIKSAAN THRESHOLD SENSOR
     *
     * Hanya pembacaan yang lolos pemeriksaan
     * Z-Score yang diproses sebagai threshold.
     */
    private function checkThreshold(Sensor $sensor): void
    {
        $setting = Setting::first();

        if (!$setting) {
            return;
        }

        $parameters = [
            'ph' => [
                'label' => 'pH Level',
                'status' => 'ph_status',
            ],
            'liquid_temperature' => [
                'label' => 'Liquid Temperature',
                'status' => 'temp_status',
            ],
        ];

        foreach ($parameters as $field => $config) {
            $type = "threshold_{$field}";
            $label = $config['label'];
            $status = $sensor->{$config['status']};

            // Jangan memeriksa threshold pada data
            // yang belum valid atau terdeteksi anomali.
            if ($status !== 'normal') {
                continue;
            }

            $rawValue = $sensor->{$field};

            if (
                $rawValue === null ||
                !is_numeric($rawValue) ||
                !is_finite((float) $rawValue)
            ) {
                continue;
            }

            $value = (float) $rawValue;

            $minKey = "{$field}_min";
            $maxKey = "{$field}_max";

            $min = $setting->{$minKey} !== null
                ? (float) $setting->{$minKey}
                : null;

            $max = $setting->{$maxKey} !== null
                ? (float) $setting->{$maxKey}
                : null;

            // Jangan menyimpulkan normal jika
            // kedua batas belum dikonfigurasi.
            if ($min === null && $max === null) {
                continue;
            }

            // Nilai melebihi batas maksimum.
            if ($max !== null && $value > $max) {
                $message = "Sensor {$label} bernilai {$value}, "
                    . "melampaui batas maksimum ({$max})!";

                $this->handleAlert(
                    $sensor,
                    $type,
                    'danger',
                    $message
                );

                continue;
            }

            // Nilai di bawah batas minimum.
            if ($min !== null && $value < $min) {
                $message = "Sensor {$label} bernilai {$value}, "
                    . "di bawah batas minimum ({$min})!";

                $this->handleAlert(
                    $sensor,
                    $type,
                    'warning',
                    $message
                );

                continue;
            }

            // Pembacaan valid dan berada di dalam
            // batas yang dikonfigurasi.
            $this->resolveAlert($sensor, $type);
        }
    }

    /**
     * 3. MEMBUAT ATAU MEMPERBARUI ALERT AKTIF
     *
     * Satu alert aktif untuk satu perangkat
     * dan satu jenis parameter.
     *
     * updated_at digunakan sebagai waktu
     * pengiriman notifikasi terakhir.
     */
    private function handleAlert(
        Sensor $sensor,
        string $type,
        string $level,
        string $message
    ): void {
        $activeAlert = $this->activeAlertQuery(
            $sensor,
            $type
        )->first();

        if ($activeAlert) {

            // Jika tingkat bahaya berubah, tutup
            // kondisi sebelumnya dan buat alert baru.
            if ($activeAlert->level !== $level) {
                $this->resolveAlert($sensor, $type);

                $this->createAlert(
                    $sensor,
                    $type,
                    $level,
                    $message
                );

                return;
            }

            // Selama cooldown, jangan membuat alert
            // baru ataupun mengirim ulang WhatsApp.
            $lastNotificationAt = $activeAlert->updated_at
                ?? $activeAlert->created_at;

            if (
                $lastNotificationAt &&
                $lastNotificationAt->greaterThan(
                    now()->subMinutes(self::COOLDOWN_MINUTES)
                )
            ) {
                return;
            }

            // Kondisi masih abnormal setelah cooldown.
            // Gunakan alert aktif yang sama.
            $activeAlert->update([
                'sensor_id' => $sensor->id,
                'message' => $message,
            ]);

            $this->sendWhatsAppAlert($activeAlert);

            return;
        }

        $this->createAlert(
            $sensor,
            $type,
            $level,
            $message
        );
    }

    /**
     * 4. MENYIMPAN ALERT BARU
     */
    private function createAlert(
        Sensor $sensor,
        string $type,
        string $level,
        string $message
    ): void {
        $alert = Alert::create([
            'sensor_id' => $sensor->id,
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'resolved_at' => null,
        ]);

        $this->sendWhatsAppAlert($alert);
    }

    /**
     * 5. QUERY ALERT AKTIF PER PERANGKAT
     */
    private function activeAlertQuery(
        Sensor $sensor,
        string $type
    ) {
        return Alert::query()
            ->where('type', $type)
            ->whereNull('resolved_at')
            ->whereHas('sensor', function ($query) use ($sensor) {
                $query->where(
                    'device_id',
                    $sensor->device_id
                );
            });
    }

    /**
     * 6. MENYELESAIKAN ALERT
     *
     * Alert tidak dihapus dari database,
     * sehingga riwayat tetap tersimpan.
     */
    private function resolveAlert(
        Sensor $sensor,
        string $type
    ): void {
        $this->activeAlertQuery($sensor, $type)
            ->update([
                'resolved_at' => now(),
            ]);
    }

    /**
     * 7. MENGIRIM NOTIFIKASI WHATSAPP
     *
     * Menggunakan Fonnte API melalui
     * WhatsAppService yang sudah tersedia.
     */
    private function sendWhatsAppAlert(Alert $alert): void
    {
        // Ambil nomor penerima dari pengaturan.
        $phone = Setting::query()
            ->value('whatsapp_number');

        // Gunakan nomor cadangan jika kosong.
        if (blank($phone)) {
            $phone = config(
                'services.fonnte.admin_phone'
            );
        }

        if (blank($phone)) {
            return;
        }

        $message = "PERINGATAN ENZYLIFE\n\n"
            . "Tingkat: " . strtoupper($alert->level) . "\n"
            . "Jenis: " . $alert->type . "\n"
            . "Pesan: " . $alert->message . "\n"
            . "Waktu: "
            . $alert->created_at->format('d-m-Y H:i:s')
            . "\n\n"
            . "Silakan periksa dashboard EnzyLife.";

        app(WhatsAppService::class)->sendMessage(
            $phone,
            $message
        );
    }
}
