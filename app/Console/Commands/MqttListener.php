<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\Setting;
use App\Services\SensorDataProcessor;
use App\Services\ZScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\MqttClient;

class MqttListener extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description =
        'Listen MQTT data from HiveMQ and save to database';

    protected function createClient(
        string $server,
        int $port,
        string $clientId
    ): MqttClientContract {
        return new MqttClient(
            $server,
            $port,
            $clientId
        );
    }

    /**
     * Mengevaluasi threshold semua sensor terhadap tabel settings:
     * - Membuat alert aktif jika di luar rentang [min, max]
     * - Menutup alert aktif (set resolved_at) & mencatat nilai saat kembali normal
     */
    protected function evaluateSensorThresholds(Sensor $sensor): void
    {
        $setting = Setting::first();

        if (!$setting) {
            return;
        }

        $parameters = [
            'ph' => [
                'value' => $sensor->ph,
                'label' => 'pH',
                'unit' => '',
            ],
            'temperature' => [
                'value' => $sensor->temperature,
                'label' => 'Suhu Udara',
                'unit' => ' °C',
            ],
            'liquid_temperature' => [
                'value' => $sensor->liquid_temperature,
                'label' => 'Suhu Cairan',
                'unit' => ' °C',
            ],
            'gas' => [
                'value' => $sensor->gas,
                'label' => 'Gas',
                'unit' => '',
            ],
            'humidity' => [
                'value' => $sensor->humidity,
                'label' => 'Kelembapan',
                'unit' => '%',
            ],
        ];

        foreach ($parameters as $key => $meta) {
            $rawValue = $meta['value'];

            if ($rawValue === null || !is_numeric($rawValue) || !is_finite((float) $rawValue)) {
                continue;
            }

            $value = (float) $rawValue;
            $min = $setting->{$key . '_min'};
            $max = $setting->{$key . '_max'};

            if ($min === null && $max === null) {
                continue;
            }

            $belowMin = $min !== null && $value < (float) $min;
            $aboveMax = $max !== null && $value > (float) $max;
            $outOfRange = $belowMin || $aboveMax;
            $alertType = 'threshold_' . $key;

            $activeQuery = Alert::query()
                ->where('type', $alertType)
                ->whereNull('resolved_at')
                ->whereHas('sensor', function ($q) use ($sensor) {
                    $q->where('device_id', $sensor->device_id);
                });

            if ($outOfRange) {
                if (!(clone $activeQuery)->exists()) {
                    $detail = $belowMin
                        ? "terlalu rendah (di bawah batas min {$min}{$meta['unit']})"
                        : "terlalu tinggi (melebihi batas max {$max}{$meta['unit']})";

                    Alert::create([
                        'sensor_id' => $sensor->id,
                        'type' => $alertType,
                        'message' => "{$meta['label']} ({$value}{$meta['unit']}) {$detail}.",
                        'level' => 'warning',
                        'resolved_at' => null,
                    ]);

                    $this->warn("Alert threshold dibuat: {$alertType} ({$value})");
                }
            } else {
                $openAlerts = (clone $activeQuery)->get();

                if ($openAlerts->isNotEmpty()) {
                    $now = now();

                    foreach ($openAlerts as $openAlert) {
                        $updatedMessage = rtrim($openAlert->message, '.')
                            . " — Kembali normal di {$value}{$meta['unit']}.";

                        $openAlert->update([
                            'message' => $updatedMessage,
                            'resolved_at' => $now,
                        ]);
                    }

                    $this->info("Alert threshold selesai: {$alertType} kembali normal ({$value})");
                }
            }
        }
    }

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
        // Topik konfirmasi data yang telah tersimpan
        $ackTopic = 'enzylife/device01/ack';

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
        $mqtt = $this->createClient(
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
            ) use ($sensorDataProcessor, $mqtt, $ackTopic) {
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
                    // 5A. PEMERIKSAAN DUPLIKASI DATA
                    // ==================================

                    $readingId = $data['reading_id'] ?? null;

                    if ($readingId !== null) {
                        if (
                            !is_string($readingId) ||
                            trim($readingId) === '' ||
                            strlen($readingId) > 100
                        ) {
                            $this->warn('Invalid reading_id.');
                            return;
                        }

                        $readingId = trim($readingId);

                        $alreadyExists = Sensor::where(
                            'reading_id',
                            $readingId
                        )->exists();

                        if ($alreadyExists) {
                            $this->info(
                                'Duplicate sensor reading skipped: ' . $readingId
                            );

                            // Data sudah tersimpan sebelumnya.
                            // Kirim ACK kembali agar ESP32 tidak mengulang terus.
                            $mqtt->publish(
                                $ackTopic,
                                json_encode([
                                    'reading_id' => $readingId,
                                    'status' => 'saved',
                                ], JSON_THROW_ON_ERROR),
                                1
                            );

                            return;
                        }
                    }

                    // ==================================
                    // 6. UPDATE STATUS SD CARD
                    // ==================================

                    // Saat ini EnzyLife menggunakan Device ID 1.
                    $deviceId = 1;

                    $device = Device::find($deviceId);

                    if ($device) {
                        // Perbarui status hanya jika ESP32
                        // benar-benar mengirim field sd_available.
                        if (
                            array_key_exists('sd_available', $data) &&
                            is_bool($data['sd_available'])
                        ) {
                            $sdAvailable = $data['sd_available'];

                            $newStatus = $sdAvailable
                                ? 'CONNECTED'
                                : 'DISCONNECTED';

                            if ($device->sd_status !== $newStatus) {
                                $device->update([
                                    'sd_status' => $newStatus,
                                ]);
                            }

                            $this->info(
                                'SD Card status updated to: ' . $newStatus
                            );
                        } else {
                            // Tidak ada informasi status yang valid.
                            // Jangan menganggap MicroSD terputus.
                            $this->warn(
                                'SD Card status not provided. Keeping previous status.'
                            );
                        }
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
                        'reading_id' => $readingId,

                        'recorded_at' => isset($data['timestamp'])
                            && is_string($data['timestamp'])
                            ? $data['timestamp']
                            : null,

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

                    // Evaluasi batas threshold semua sensor & auto-resolve jika normal
                    $this->evaluateSensorThresholds($sensor);

                    $this->info(
                        'Data saved to database. Sensor ID: '
                        . $sensor->id
                    );

                    // ==================================
                    // KONFIRMASI PENYIMPANAN DATA (ACK)
                    // ==================================

                    if ($readingId !== null) {
                        $ackPayload = json_encode([
                            'reading_id' => $readingId,
                            'status' => 'saved',
                        ], JSON_THROW_ON_ERROR);

                        $mqtt->publish(
                            $ackTopic,
                            $ackPayload,
                            1
                        );

                        $this->info(
                            'ACK sent for reading_id: ' . $readingId
                        );
                    }

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
            1
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