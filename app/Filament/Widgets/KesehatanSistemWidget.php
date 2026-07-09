<?php

namespace App\Filament\Widgets;

use App\Models\Device; // 1. Ubah dari Setting menjadi Device sesuai migrasi tabel devices
use Filament\Widgets\Widget;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Filament\Notifications\Notification;

class KesehatanSistemWidget extends Widget
{
    protected string $view = 'filament.widgets.kesehatan-sistem-widget';

    public $sdStatus = 'DISCONNECTED';

    public function mount()
    {
        $this->updateStatus();
    }

    public function updateStatus()
    {
        // Cari langsung record pertama di tabel devices menggunakan primary key 'id'
        $device = Device::find(1);
        
        $this->sdStatus = $device ? $device->sd_status : 'DISCONNECTED';
    }

    public function ejectSDCard()
    {
        $server = '801f15e13a2145c89a3e83ce6fa60601.s1.eu.hivemq.cloud';
        $port = 8883;

        $settings = (new ConnectionSettings)
            ->setUsername('enzylife')
            ->setPassword('Enzylife123')
            ->setUseTls(true)
            ->setConnectTimeout(5);

        try {
            $mqtt = new MqttClient($server, $port, 'laravel-ejector-' . uniqid());
            $mqtt->connect($settings);
            
            // Mengirim perintah ke topik device01
            $mqtt->publish('enzylife/device01/cmd', 'EJECT_SD', 0);
            $mqtt->disconnect();

            Notification::make()
                ->title('Perintah Eject Dikirim')
                ->body('Menunggu konfirmasi aman dari alat IoT EnzyLife...')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal Mengirim Perintah')
                ->body('Koneksi broker gagal: ' . $e->getMessage())
                ->danger()
                ->send();
        }

        $this->updateStatus();
    }
}