<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;

class MqttPublisherService
{
    private string $server =
        '801f15e13a2145c89a3e83ce6fa60601.s1.eu.hivemq.cloud';

    private int $port = 8883;

    private string $commandTopic =
        'enzylife/device01/cmd';

    /**
     * Membuat client MQTT.
     *
     * Method ini dipisahkan agar bisa diganti
     * dengan mock pada pengujian otomatis.
     */
    protected function createClient(): MqttClientContract
    {
        $clientId = 'laravel-enzylife-publisher-'
            . bin2hex(random_bytes(4));

        return new MqttClient(
            $this->server,
            $this->port,
            $clientId
        );
    }

    /**
     * Mengirim perubahan interval ke ESP32.
     *
     * @param int $seconds Interval dalam detik.
     */
    public function publishInterval(int $seconds): void
    {
        // ======================================
        // 1. VALIDASI INTERVAL
        // ======================================
        if ($seconds < 5 || $seconds > 3600) {
            throw new \InvalidArgumentException(
                'Interval harus antara 5 dan 3600 detik.'
            );
        }

        // ======================================
        // 2. AMBIL KREDENSIAL HIVEMQ
        // ======================================
        $username = config('services.hivemq.username');
        $password = config('services.hivemq.password');

        if (blank($username) || blank($password)) {
            throw new \RuntimeException(
                'Kredensial HiveMQ belum dikonfigurasi.'
            );
        }

        // ======================================
        // 3. KONFIGURASI KONEKSI MQTT
        // ======================================
        $settings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls(true)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(10)
            ->setSocketTimeout(10);

        // ======================================
        // 4. BUAT CLIENT MQTT
        // ======================================
        $mqtt = $this->createClient();

        try {
            // ==================================
            // 5. HUBUNGKAN KE HIVEMQ
            // ==================================
            $mqtt->connect($settings, true);

            // ==================================
            // 6. SUSUN PERINTAH INTERVAL
            // ==================================
            $message = "SET_INTERVAL:{$seconds}";

            // ==================================
            // 7. PUBLISH PERINTAH MQTT
            // ==================================
            // QoS 1:
            // Meminta konfirmasi dari broker.
            //
            // Retain true:
            // Menyimpan konfigurasi terakhir
            // agar diterima setelah ESP32
            // melakukan subscribe kembali.
            $mqtt->publish(
                $this->commandTopic,
                $message,
                1,
                true
            );

            // Tunggu konfirmasi pengiriman
            // QoS 1 dari broker.
            $mqtt->loop(true, true);

        } finally {

            // ==================================
            // 8. PUTUSKAN KONEKSI
            // ==================================
            if ($mqtt->isConnected()) {
                $mqtt->disconnect();
            }
        }
    }
}
