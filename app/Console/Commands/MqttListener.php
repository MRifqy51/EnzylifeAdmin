<?php

namespace App\Console\Commands;

use App\Models\Sensor;
use App\Models\Device;
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
        $server = '801f15e13a2145c89a3e83ce6fa60601.s1.eu.hivemq.cloud';
        $port = 8883;
        $clientId = 'laravel-enzylife-' . uniqid();

        $username = 'enzylife';
        $password = 'Enzylife123';

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

        // false = clean session off, wajib kalau pakai auto reconnect
        $mqtt->connect($settings, false);

        $this->info('Connected. Listening topic: ' . $topic);

        $mqtt->subscribe($topic, function (string $topic, string $message) {
            $this->info('Message received: ' . $message);

            $data = json_decode($message, true);

            if (!$data) {
                $this->error('Invalid JSON');
                return;
            }

            try {
        // ===== 1. LOGIKA UPDATE STATUS SD CARD KESESUAIAN ALAT =====
        $sdAvailable = $data['sd_available'] ?? false;

        // Pastikan menggunakan model Device dan mencari ID 1
        $device = Device::find(1);

        if ($device) {
            $currentStatus = $device->sd_status;
            
            if (!$sdAvailable && $currentStatus === 'CONNECTED') {
                $newStatus = 'EJECTED';
            } else {
                $newStatus = $sdAvailable ? 'CONNECTED' : 'DISCONNECTED';
            }

            $device->update([
                'sd_status' => $newStatus
            ]);
            $this->info('SD Card status updated to: ' . $newStatus);
        }

                // ===== 2. PROSES PENYIMPANAN DATA SENSOR BAWAAN =====
                $sensor = Sensor::create([
                    'device_id' => 1,
                    'temperature' => $data['temperature'] ?? null,
                    'liquid_temperature' => $data['liquid_temperature'] ?? null,
                    'humidity' => $data['humidity'] ?? null,
                    'gas' => $data['gas'] ?? null,
                    'ph' => $data['ph'] ?? null,
                ]);

                // Penghapusan otomatis data lama jika melebihi 100 baris
                $maxData = 100;
                $oldSensorIds = Sensor::latest()
                    ->skip($maxData)
                    ->take(PHP_INT_MAX)
                    ->pluck('id');

                Sensor::whereIn('id', $oldSensorIds)->delete();

                $this->info('Data saved to database.');

                // Pemicu Real-time Event untuk Livewire/Filament
                Event::dispatch('sensor-updated', $sensor->toArray());

            } catch (\Exception $e) {
                $this->error('Database error: ' . $e->getMessage());
            }
        }, 0);

        $mqtt->loop(true, true);
    }
}