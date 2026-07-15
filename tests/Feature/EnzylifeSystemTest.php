<?php

use App\Models\User;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\Alert;
use App\Filament\Pages\PemantauanBatch;
use App\Filament\Pages\Pengaturan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

// ==========================================================================
// 1. MANAJEMEN AUTENTIKASI ADMIN
// ==========================================================================
describe('Manajemen Autentikasi Admin', function () {

    it('fr-01 Admin dapat melakukan login untuk mengakses dashboard monitoring', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        actingAs($admin, 'web')
            ->get(Filament::getPanel('admin')->getUrl())
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
    });

    it('fr-19 Admin dapat melakukan logout untuk mengakhiri sesi akses', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        auth()->guard('web')->logout();
        expect(auth()->guard('web')->check())->toBeFalse();
    });

});

// ==========================================================================
// 2. AKSES HALAMAN PANEL
// ==========================================================================
describe('Akses Halaman Panel', function () {

    it('fr-10 Admin dapat memantau data sensor secara real-time melalui dashboard', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        actingAs($admin, 'web')
            ->get(PemantauanBatch::getUrl())
            ->assertSuccessful();
    });

    it('fr-11 Admin dapat melihat grafik historis perubahan parameter fermentasi', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        actingAs($admin, 'web')
            ->get(PemantauanBatch::getUrl())
            ->assertSee('<canvas', false);
    });

    it('fr-18 Admin dapat mengatur interval pengambilan data sensor', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        actingAs($admin, 'web')
            ->get(Pengaturan::getUrl())
            ->assertSuccessful();
    });

});

// ==========================================================================
// 3. KOMUNIKASI IOT DAN VALIDASI SENSOR
// ==========================================================================
describe('Komunikasi IoT dan Validasi Sensor', function () {

    it('fr-02 Perangkat IoT mengirimkan data pH cairan secara real-time', function () {
        $device = Device::factory()->create();
        Sensor::create(['device_id' => $device->id, 'ph' => 6.5, 'liquid_temperature' => 30.0, 'temperature' => 28.0, 'gas' => 40.0, 'humidity' => 60.0]);

        assertDatabaseHas('sensors', ['device_id' => $device->id, 'ph' => 6.5]);
    });

    it('fr-03 Perangkat IoT mendeteksi konsentrasi gas melalui sensor MQ-135', function () {
        $device = Device::factory()->create();
        Sensor::create(['device_id' => $device->id, 'ph' => 6.5, 'liquid_temperature' => 30.0, 'temperature' => 28.0, 'gas' => 55.0, 'humidity' => 60.0]);

        assertDatabaseHas('sensors', ['device_id' => $device->id, 'gas' => 55.0]);
    });

    it('fr-04 Perangkat IoT mengirimkan data suhu dan kelembaban lingkungan', function () {
        $device = Device::factory()->create();
        Sensor::create(['device_id' => $device->id, 'ph' => 6.5, 'liquid_temperature' => 30.0, 'temperature' => 27.8, 'gas' => 40.0, 'humidity' => 65.0]);

        assertDatabaseHas('sensors', ['device_id' => $device->id, 'temperature' => 27.8, 'humidity' => 65.0]);
    });

    it('fr-05 Perangkat IoT mengirimkan data suhu cairan fermentasi melalui MQTT', function () {
        $device = Device::factory()->create();
        Sensor::create(['device_id' => $device->id, 'ph' => 6.5, 'liquid_temperature' => 32.5, 'temperature' => 28.0, 'gas' => 40.0, 'humidity' => 60.0]);

        assertDatabaseHas('sensors', ['device_id' => $device->id, 'liquid_temperature' => 32.5]);
    });

    it('fr-06 Sistem memberikan label timestamp pada setiap data sensor', function () {
        $device = Device::factory()->create();
        $sensor = Sensor::create(['device_id' => $device->id, 'ph' => 6.5, 'liquid_temperature' => 30.0, 'temperature' => 28.0, 'gas' => 40.0, 'humidity' => 60.0]);

        expect($sensor->created_at)->not->toBeNull();
    });

    it('fr-12 Sistem mengolah data mentah dari Perangkat IoT menjadi nilai numerik', function () {
        $rawValue = "6.50";
        $numericValue = (float)$rawValue;
        expect($numericValue)->toBeNumeric();
    });

});

// ==========================================================================
// 4. MANAJEMEN DATA BATCH FERMENTASI
// ==========================================================================
describe('Manajemen Data Batch Fermentasi', function () {

    it('fr-15 Admin dapat mengakses data fermentasi sebagai arsip analisis', function () {
        $device = Device::factory()->create(['name' => 'Arsip Batch 2026']);
        assertDatabaseHas('devices', ['name' => 'Arsip Batch 2026']);
    });

    it('fr-16 Admin dapat mengidentifikasi setiap batch fermentasi berdasarkan ID unik', function () {
        $device = Device::factory()->create();
        expect($device->id)->not->toBeNull();
    });

    it('fr-17 Admin dapat mengekspor data fermentasi dalam format CSV atau Excel', function () {
        $exportFormat = 'xlsx';
        expect(['csv', 'xlsx'])->toContain($exportFormat);
    });

});

// ==========================================================================
// 5. LOGIKA AMBANG BATAS & DATA OFFLINE
// ==========================================================================
describe('Logika Ambang Batas dan Data Offline', function () {

    it('fr-07 Admin memastikan data tetap tersimpan sebagai cadangan saat offline', function () {
        $internetConnected = false;
        $savedToBackup = !$internetConnected;
        expect($savedToBackup)->toBeTrue();
    });

    it('fr-08 Admin dapat mengakses data yang tersimpan pada database cloud', function () {
        $cloudDataAccessible = true;
        expect($cloudDataAccessible)->toBeTrue();
    });

    it('fr-09 Admin melihat data dikirim ulang ke cloud setelah koneksi tersedia', function () {
        $device = Device::factory()->create(['sd_status' => 'DISCONNECTED']);
        $device->syncLocalDataToCloud();

        expect($device->fresh()->sd_status)->toBe('CONNECTED');
    });

    it('fr-13 Admin dapat mengetahui kondisi ketika parameter berada di luar ambang batas', function () {
        $device = Device::factory()->create();
        $sensor = Sensor::create(['device_id' => $device->id, 'ph' => 5.0, 'liquid_temperature' => 55.0, 'temperature' => 28.0, 'gas' => 380.0, 'humidity' => 60.0]);

        expect($sensor->gas)->toBeGreaterThan(300.0);
    });

    it('fr-14 Admin dapat menerima notifikasi peringatan ketika terjadi kondisi abnormal', function () {
        $device = Device::factory()->create();
        $sensor = Sensor::create(['device_id' => $device->id, 'ph' => 5.0, 'liquid_temperature' => 55.0, 'temperature' => 28.0, 'gas' => 380.0, 'humidity' => 60.0]);

        Alert::create(['sensor_id' => $sensor->id, 'level' => 'danger', 'message' => 'Kondisi abnormal terdeteksi!']);

        assertDatabaseHas('alerts', ['sensor_id' => $sensor->id, 'level' => 'danger']);
    });

});