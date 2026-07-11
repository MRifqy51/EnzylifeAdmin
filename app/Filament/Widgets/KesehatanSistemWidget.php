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

    // Pastikan ini aktif agar widget refresh setiap 5 detik
    protected static ?string $pollingInterval = '5s';

    public $sdStatus = 'DISCONNECTED';

    // mount dipanggil saat halaman pertama dimuat
    public function mount()
    {
        $this->updateStatus();
    }

    // Fungsi ini dipanggil otomatis oleh Filament saat polling interval tercapai
    public function updateStatus()
    {
        // 1. Ambil data paling baru dengan query yang bersih
        $device = Device::query()->where('id', 1)->first();
        
        // 2. Update variabel $sdStatus
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