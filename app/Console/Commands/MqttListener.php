<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Models\Device;
use App\Services\ZScoreService;
use App\Services\SensorDataProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListener extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description =
        'Listen MQTT data from HiveMQ and save to database';

    /**
     * Menjalankan MQTT Listener EnzyLife.
     */
    public function handle()
    {
        // ==========================================
        // 1. INISIALISASI SERVICE
        // ==========================================

        $zscoreService = new ZScoreService();

        $sensorDataProcessor = new SensorDataProcessor(
            $zscoreService
        );

        // ==========================================
        // 2. KONFIGURASI MQTT HIVEMQ
        // ==========================================

        $server = '801f15e13a2145c89a3e83ce6fa60601.s1.eu.hivemq.cloud';

        $port = 8883;

        $clientId = 'laravel-enzylife-' . uniqid();

        // Kredensial diambil dari konfigurasi Laravel.
        $username = config('services.hivemq.username');
        $password = config('services.hivemq.password');

        // Topik ESP32 EnzyLife.
        $topic = 'enzylife/device01';

        // Konfigurasi koneksi MQTT.
        $settings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls(true)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(10)
            ->setSocketTimeout(10)
            ->setReconnectAutomatically(true);

        // Membuat MQTT Client.
        $mqtt = new MqttClient(
            $server,
            $port,
            $clientId
        );

        $this->info('Connecting to HiveMQ...');

        // ==========================================
        // 3. MEMBUKA KONEKSI MQTT
        // ==========================================

        try {
            $mqtt->connect($settings, false);

            $this->info(
                'Connected. Listening topic: ' . $topic
            );

        } catch (\Throwable $e) {

            $this->error(
                'MQTT connection failed: ' . $e->getMessage()
            );

            Log::error(
                'EnzyLife MQTT connection failed',
                [
                    'exception' => $e,
                ]
            );

            return self::FAILURE;
        }

        // ==========================================
        // 4. SUBSCRIBE PESAN MQTT
        // ==========================================

        $mqtt->subscribe(
            $topic,
            function (
                string $topic,
                string $message
            ) use ($sensorDataProcessor) {

                try {

                    // ==================================
                    // 5. DECODE DAN VALIDASI JSON
                    // ==================================

                    $data = json_decode(
                        $message,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                    // Payload harus berbentuk objek JSON.
                    if (
                        !is_array($data) ||
                        array_is_list($data)
                    ) {
                        $this->warn(
                            'Payload MQTT bukan objek JSON.'
                        );

                        return;
                    }

                    // Validasi field wajib.
                    $invalidField = $sensorDataProcessor
                        ->validateRequiredFields($data);

                    if ($invalidField !== null) {

                        $this->warn(
                            "Invalid or missing MQTT field: {$invalidField}"
                        );

                        Log::warning(
                            'EnzyLife invalid MQTT sensor field',
                            [
                                'topic' => $topic,
                                'field' => $invalidField,
                            ]
                        );

                        return;
                    }

                    $this->info(
                        'Valid MQTT sensor data received.'
                    );

                    // ==================================
                    // 6. UPDATE STATUS SD CARD
                    // ==================================

                    $sdAvailable = $data['sd_available'] ?? false;

                    // Saat ini EnzyLife menggunakan
                    // perangkat dengan ID 1.
                    $deviceId = 1;

                    $device = Device::find($deviceId);

                    if ($device) {

                        $currentStatus = $device->sd_status;

                        if (
                            !$sdAvailable &&
                            $currentStatus === 'CONNECTED'
                        ) {
                            $newStatus = 'EJECTED';

                        } else {

                            $newStatus = $sdAvailable
                                ? 'CONNECTED'
                                : 'DISCONNECTED';
                        }

                        $device->update([
                            'sd_status' => $newStatus,
                        ]);

                        $this->info(
                            'SD Card status updated to: '
                            . $newStatus
                        );
                    }

                    // ==================================
                    // 7. ANALISIS DATA SENSOR Z-SCORE
                    // ==================================

                    $analysis = $sensorDataProcessor
                        ->analyzeReadings(
                            $data,
                            $deviceId
                        );

                    // Mengambil data hasil analisis.
                    $phRaw = $analysis['ph_raw'];
                    $tempRaw = $analysis['temp_raw'];

                    $phStatus = $analysis['ph_status'];
                    $tempStatus = $analysis['temp_status'];

                    $isAnomaly = $analysis['is_anomaly'];

                    $anomalyStatus =
                        $analysis['anomaly_status'];

                    // ==================================
                    // 8. SIMPAN DATA SENSOR KE DATABASE
                    // ==================================

                    $sensor = Sensor::create([

                        'device_id' => $deviceId,

                        // Sensor lingkungan.
                        'temperature' =>
                            $data['temperature'] ?? null,

                        'humidity' =>
                            $data['humidity'] ?? null,

                        'gas' =>
                            $data['gas'] ?? null,

                        // Nilai pH dan suhu cairan
                        // untuk dashboard EnzyLife.
                        'ph' => $phRaw,

                        'liquid_temperature' => $tempRaw,

                        // Data mentah untuk Z-Score.
                        'ph_raw' => $phRaw,

                        'temp_raw' => $tempRaw,

                        // Hasil perhitungan Z-Score.
                        'z_score_ph' =>
                            $analysis['z_score_ph'],

                        'z_score_temp' =>
                            $analysis['z_score_temp'],

                        // Status anomali gabungan.
                        'is_anomaly' => $isAnomaly,

                        'anomaly_status' => $anomalyStatus,

                        // Status per parameter.
                        'ph_status' => $phStatus,

                        'temp_status' => $tempStatus,
                    ]);

                    /*
                     * Sensor::create() juga akan
                     * memicu SensorObserver jika
                     * observer sudah terdaftar.
                     *
                     * SensorObserver menangani:
                     * - Alert Z-Score
                     * - Alert threshold
                     * - Cooldown WhatsApp
                     * - Status alert aktif
                     * - Penyelesaian alert
                     */

                    // Data historis tidak dihapus
                    // agar tetap tersedia untuk
                    // monitoring dan perhitungan Z-Score.

                    $this->info(
                        'Data saved to database. Sensor ID: '
                        . $sensor->id
                    );

                    // ==================================
                    // 9. PEMBARUAN DATA DASHBOARD
                    // ==================================

                    Event::dispatch(
                        'sensor-updated',
                        $sensor->toArray()
                    );

                    $this->info(
                        'Sensor update event dispatched.'
                    );

                    // ==================================
                    // 10. PENANGANAN JSON TIDAK VALID
                    // ==================================

                } catch (\JsonException $e) {

                    $this->warn(
                        'Payload MQTT memiliki format JSON tidak valid.'
                    );

                    Log::warning(
                        'EnzyLife received invalid MQTT JSON',
                        [
                            'topic' => $topic,
                            'error' => $e->getMessage(),
                        ]
                    );

                    // ==================================
                    // 11. PENANGANAN ERROR PEMROSESAN
                    // ==================================

                } catch (\Throwable $e) {

                    $this->error(
                        'MQTT message processing error: '
                        . $e->getMessage()
                    );

                    Log::error(
                        'EnzyLife MQTT processing failed',
                        [
                            'topic' => $topic,
                            'exception' => $e,
                        ]
                    );
                }

            },
            0
        );

        // ==========================================
        // 12. MENJAGA MQTT LISTENER TETAP AKTIF
        // ==========================================

        $this->info(
            'EnzyLife MQTT Listener is running...'
        );

        try {

            $mqtt->loop(true, true);

        } catch (\Throwable $e) {

            $this->error(
                'MQTT listener stopped: '
                . $e->getMessage()
            );

            Log::error(
                'EnzyLife MQTT listener stopped',
                [
                    'exception' => $e,
                ]
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
