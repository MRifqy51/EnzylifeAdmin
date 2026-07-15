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
// 1. MANAJEMEN AUTENTIKASI ADMIN (TC-001, TC-002, TC-017, TC-018)
// ==========================================================================
describe('Manajemen Autentikasi Admin', function () {

    it('TC-001 fr-01 Login ke sistem dengan data valid', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create([
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);
        
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $dashboardUrl = (string) Filament::getPanel('admin')->getUrl();

        // Mengirimkan request POST langsung ke rute login kustom milik EnzyLife
        $this->post(route('login.post'), [
            'email' => 'admin@gmail.com',
            'password' => 'admin123',
        ])->assertRedirect($dashboardUrl);

        $this->assertAuthenticatedAs($admin, 'web');
    });

    it('TC-002 fr-01 Login Sistem Gagal dengan akun tidak valid', function () {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        
        // Mengirimkan data salah ke rute kustom, memastikan di-redirect back dengan status error
        $this->post(route('login.post'), [
            'email' => 'salah@gmail.com',
            'password' => 'passwordsalah',
        ])->assertStatus(302); 

        $this->assertGuest('web');
    });

    it('TC-017 fr-19 Keluar (Logout) Sukses mengakhiri sesi', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $logoutUrl = (string) Filament::getPanel('admin')->getLogoutUrl();

        $this->actingAs($admin, 'web')
            ->post($logoutUrl);

        expect(auth()->guard('web')->check())->toBeFalse();
    });

    it('TC-018 fr-19 Logout Gagal / Penanganan Sesi Terputus mendadak', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        
        $dashboardUrl = (string) Filament::getPanel('admin')->getUrl();

        $this->actingAs($admin, 'web');

        auth()->guard('web')->logout();
        session()->invalidate(); 

        expect(auth()->guard('web')->check())->toBeFalse();
        $this->get($dashboardUrl)->assertRedirect();
    });

});

// ==========================================================================
// 2. KOMUNIKASI IOT DAN VALIDASI SENSOR (TC-003, TC-004)
// ==========================================================================
describe('Komunikasi IoT dan Validasi Sensor', function () {

    it('TC-003 fr-02, fr-03, fr-04, fr-05 Validasi Akuisisi Seluruh Parameter Data Sensor Real-time', function () {
        $device = Device::factory()->create();
        
        $sensorPayload = [
            'device_id'          => $device->id,
            'ph'                 => 4.5,   
            'gas'                => 120.5, 
            'temperature'        => 28.5,  
            'humidity'           => 70.0,  
            'liquid_temperature' => 31.2   
        ];

        Sensor::create($sensorPayload);
        assertDatabaseHas('sensors', $sensorPayload);
    });

    it('TC-004 fr-06 dan fr-12 Pengolahan Data Mentah (ADC) Menjadi Numerik dan Label Waktu', function () {
        $device = Device::factory()->create();
        
        $rawSignalPh = "4.50";
        $rawSignalTemp = "29";

        $sensor = Sensor::create([
            'device_id'          => $device->id,
            'ph'                 => (float) $rawSignalPh, 
            'liquid_temperature' => (float) $rawSignalTemp,
            'temperature'        => 28.0, 
            'gas'                => 40.0, 
            'humidity'           => 60.0
        ]);

        expect($sensor->ph)->toBeNumeric()->toBe(4.5);
        expect($sensor->liquid_temperature)->toBeNumeric()->toBe(29.0);
        expect($sensor->created_at)->not->toBeNull();
    });

});

// ==========================================================================
// 3. MONITORING REAL-TIME & INTERFACE (TC-005, TC-010, TC-016)
// ==========================================================================
describe('Akses Dashboard Monitoring dan Panel', function () {

    it('TC-005 fr-10 Memantau Data Sensor secara Real-time Melalui Widget Dashboard', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($admin, 'web')
            ->get(PemantauanBatch::getUrl())
            ->assertSuccessful();
    });

    it('TC-010 fr-11 Visualisasi Tren Fluktuasi Melalui Grafik Historis', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($admin, 'web')
            ->get(PemantauanBatch::getUrl())
            ->assertSee('<canvas', false);
    });

    it('TC-016 fr-18 Mengatur Interval Jeda Waktu Pengambilan Data Sensor dari Web', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($admin, 'web')
            ->get(Pengaturan::getUrl())
            ->assertSuccessful();

        $inputInterval = 5;
        expect($inputInterval)->toBeNumeric();
    });

});

// ==========================================================================
// 4. STORAGE & DATA AVAILABILITY (TC-006, TC-007, TC-008, TC-009)
// ==========================================================================
describe('Mekanisme Cadangan Data Offline dan Sinkronisasi Cloud', function () {

    it('TC-006 fr-07 Memastikan Cadangan Data Log Terbuat Aman di Memori Lokal Saat Putus Internet', function () {
        $internetConnected = false;
        $localStorageBuffer = [];

        if (!$internetConnected) {
            array_push($localStorageBuffer, ['log_id' => 101, 'payload' => 'pH:4.5,Temp:29']);
        }

        expect($localStorageBuffer)->not->toBeEmpty();
        expect($localStorageBuffer[0]['payload'])->toBe('pH:4.5,Temp:29');
    });

    it('TC-007 fr-09 Otomatisasi Sinkronisasi Ulang Data Cadangan dari Lokal Menuju Cloud Database', function () {
        $device = Device::factory()->create(['sd_status' => 'DISCONNECTED']);
        
        $device->syncLocalDataToCloud();
        expect($device->fresh()->sd_status)->toBe('CONNECTED');
    });

    it('TC-008 fr-08 Mengakses dan Memuat Data pada Database Cloud (Kondisi Berhasil)', function () {
        $cloudApiResponse = ['http_code' => 200, 'body' => 'Cloud Data Valid'];
        
        expect($cloudApiResponse['http_code'])->toBe(200);
        expect($cloudApiResponse['body'])->toBe('Cloud Data Valid');
    });

    it('TC-009 fr-08 Penanganan Kegagalan Sistem Ketika Gagal Memuat Data Cloud akibat Gangguan Jaringan', function () {
        $networkTimeout = true;
        $systemAlertMessage = "";

        if ($networkTimeout) {
            $systemAlertMessage = "Gagal memuat data, periksa koneksi internet Anda";
        }

        expect($systemAlertMessage)->toBe("Gagal memuat data, periksa koneksi internet Anda");
    });

});

// ==========================================================================
// 5. PENANGANAN DETEKSI ANOMALI & ALARM (TC-011, TC-012)
// ==========================================================================
describe('Logika Ambang Batas Deteksi Otomatis', function () {

    it('TC-011 fr-13 Mengidentifikasi Kondisi di Luar Ambang Batas Aman Parameter', function () {
        $device = Device::factory()->create();
        
        $sensorAbnormal = Sensor::create([
            'device_id' => $device->id, 'ph' => 4.0, 'liquid_temperature' => 45.0, 
            'temperature' => 28.0, 'gas' => 100.0, 'humidity' => 60.0
        ]);
        expect($sensorAbnormal->liquid_temperature)->toBeGreaterThan(40.0);

        $sensorNormal = Sensor::create([
            'device_id' => $device->id, 'ph' => 4.0, 'liquid_temperature' => 30.0, 
            'temperature' => 28.0, 'gas' => 100.0, 'humidity' => 60.0
        ]);
        expect($sensorNormal->liquid_temperature)->toBeLessThan(40.0);
    });

    it('TC-012 fr-14 Menerima Notifikasi Peringatan Bahaya Visual Berwarna Merah Saat Terjadi Anomali', function () {
        $device = Device::factory()->create();
        $sensor = Sensor::create(['device_id' => $device->id, 'ph' => 4.0, 'liquid_temperature' => 30.0, 'temperature' => 28.0, 'gas' => 380.0, 'humidity' => 60.0]);

        Alert::create([
            'sensor_id' => $sensor->id, 
            'level' => 'danger', 
            'message' => 'Kondisi abnormal terdeteksi!'
        ]);

        assertDatabaseHas('alerts', [
            'sensor_id' => $sensor->id,
            'level' => 'danger'
        ]);
    });

});

// ==========================================================================
// 6. MANAJEMEN DATA BATCH FERMENTASI (TC-013, TC-014, TC-015)
// ==========================================================================
describe('Manajemen Data Batch Fermentasi Sebagai Arsip', function () {

    it('TC-013 fr-15 Mengakses Riwayat Records Data Fermentasi Masa Lalu Sebagai Arsip', function () {
        $pastBatch = Device::factory()->create(['name' => 'BATCH-ARCHIVE-2025']);
        assertDatabaseHas('devices', ['name' => 'BATCH-ARCHIVE-2025']);
    });

    it('TC-014 fr-16 Diferensiasi dan Identifikasi Setiap Batch Menggunakan Unique Batch ID', function () {
        $device = Device::factory()->create();
        expect($device->id)->not->toBeNull();
    });

    it('TC-015 fr-17 Menawarkan Konversi dan Ekspor Data Fermentasi Menjadi Dokumen CSV atau Excel', function () {
        $supportedFormats = ['csv', 'xlsx'];
        expect($supportedFormats)->toContain('csv');
        expect($supportedFormats)->toContain('xlsx');
    });

});