<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Models\Device;
use App\Services\ZScoreService;
use App\Services\SensorDataProcessor;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Event;


class MqttListener extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Listen MQTT data from HiveMQ and save to database';

    public function handle()
    {   
        // Inisialisasi layanan deteksi anomali
        
        $zscoreService = new ZScoreService();

        // Service baru untuk memproses data sensor
        $sensorDataProcessor = new SensorDataProcessor($zscoreService);


        // ===== 1. KONFIGURASI MQTT =====
        $server = '801f15e13a2145c89a3e83ce6fa60601.s1.eu.hivemq.cloud';
        $port = 8883;
        $clientId = 'laravel-enzylife-' . uniqid();

        // Kredensial disimpan di .env
        $username = config('services.hivemq.username');
        $password = config('services.hivemq.password');

        $topic = 'enzylife/device01';

        $settings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls(true)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(10)
            ->setSocketTimeout(10)
            ->setReconnectAutomatically(true);

        $mqtt = new MqttClient($server, $port, $clientId);

        $this->info('Connecting to HiveMQ...');

        $mqtt->connect($settings, false);

        $this->info('Connected. Listening topic: ' . $topic);

        // ===== 2. MENERIMA DATA MQTT =====
        
        $mqtt->subscribe($topic, function (string $topic, string $message) use ($zscoreService, $sensorDataProcessor) {

            $this->info('Message received: ' . $message);

            $data = json_decode($message, true);

            if (!is_array($data)) {
                $this->error('Invalid JSON');
                return;
            }

            // Validasi parameter wajib sebelum menyimpan data sensor
            
            $invalidField = $sensorDataProcessor->validateRequiredFields($data);

            if ($invalidField !== null) {
                $this->error(
                    "Invalid or missing MQTT field: {$invalidField}"
                );

                return;
            }


            try {

                // ===== 3. UPDATE STATUS SD CARD =====
                $sdAvailable = $data['sd_available'] ?? false;

                $device = Device::find(1);

                if ($device) {
                    $currentStatus = $device->sd_status;

                    if (!$sdAvailable && $currentStatus === 'CONNECTED') {
                        $newStatus = 'EJECTED';
                    } else {
                        $newStatus = $sdAvailable
                            ? 'CONNECTED'
                            : 'DISCONNECTED';
                    }

                    $device->update([
                        'sd_status' => $newStatus
                    ]);

                    $this->info(
                        'SD Card status updated to: ' . $newStatus
                    );
                }

                // Ambil data mentah dari pesan MQTT
                
                // ===== 3. ANALISIS DATA SENSOR =====

                $analysis = $sensorDataProcessor->analyzeReadings(
                    $data,
                    1
                );

                // Mengambil hasil pemrosesan sensor
                $phRaw = $analysis['ph_raw'];
                $tempRaw = $analysis['temp_raw'];

                $phStatus = $analysis['ph_status'];
                $tempStatus = $analysis['temp_status'];

                $isAnomaly = $analysis['is_anomaly'];
                $anomalyStatus = $analysis['anomaly_status'];



                // ===== 4. SIMPAN DATA SENSOR =====

                $sensor = Sensor::create([
                    'device_id' => 1,

                    // Data sensor lainnya
                    'temperature' => $data['temperature'] ?? null,
                    'humidity' => $data['humidity'] ?? null,
                    'gas' => $data['gas'] ?? null,

                    // Data pH dan suhu untuk dashboard lama
                    'ph' => $phRaw,
                    'liquid_temperature' => $tempRaw,

                    // Data mentah untuk Z-Score
                    'ph_raw' => $phRaw,
                    'temp_raw' => $tempRaw,

                    // Hasil perhitungan Z-Score                    
                    'z_score_ph' => $analysis['z_score_ph'],
                    'z_score_temp' => $analysis['z_score_temp'],
                    
                    // Status hasil analisis gabungan
                    'is_anomaly' => $isAnomaly,
                    'anomaly_status' => $anomalyStatus,

                    // Status Z-Score per parameter
                    'ph_status' => $phStatus,
                    'temp_status' => $tempStatus,

                ]);


                /*
                 * Penghapusan otomatis 100 data dinonaktifkan.
                 *
                 * Data historis dipertahankan untuk:
                 * - Monitoring fermentasi 90 hari
                 * - Perhitungan Z-Score
                 * - Pengujian dan analisis data sensor
                 */

                $this->info('Data saved to database.');

                // ===== 5. UPDATE DASHBOARD =====
                Event::dispatch(
                    'sensor-updated',
                    $sensor->toArray()
                );

            
                } catch (\Throwable $e) {

                    $this->error(
                        'MQTT message processing error: ' . $e->getMessage()
                    );

                    \Illuminate\Support\Facades\Log::error(
                        'EnzyLife MQTT processing failed',
                        [
                            'topic' => $topic,
                            'exception' => $e,
                        ]
                    );
                }


        }, 0);

        // ===== 6. MENJAGA MQTT LISTENER AKTIF =====
        $mqtt->loop(true, true);
    }
}
