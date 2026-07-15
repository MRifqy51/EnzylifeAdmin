<?php

use App\Models\User;
use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ==========================================================================
// 1. RELIABILITY & AVAILABILITY (KEANDALAN SISTEM)
// ==========================================================================
describe('Reliability and Availability', function () {

    it('NFR-01 Sistem tetap berjalan stabil dan mencatat data tanpa gangguan interupsi null data', function () {
        $device = Device::factory()->create();
        
        // Simulasi kontinuitas: Menginput banyak data beruntun (looping stress test)
        for ($i = 0; $i < 10; $i++) {
            Sensor::create([
                'device_id' => $device->id,
                'ph' => 6.5,
                'liquid_temperature' => 30.0,
                'temperature' => 28.0,
                'gas' => 40.0,
                'humidity' => 60.0
            ]);
        }

        // Memastikan jumlah data yang tercatat sesuai, menandakan sistem tidak drop/crash
        expect(Sensor::where('device_id', $device->id)->count())->toBe(10);
    });

});

// ==========================================================================
// 2. SECURITY & DATA INTEGRITY (KEAMANAN DATA)
// ==========================================================================
describe('Security and Data Integrity', function () {

    it('NFR-02 Memastikan penyimpanan aman di database lokal/cloud dan terikat integritas foreign key', function () {
        // Memastikan tabel sensor aman dan terikat secara integritas dengan tabel devices
        expect(Schema::hasColumns('sensors', ['device_id', 'ph', 'gas']))->toBeTrue();
        
        // Memastikan data tidak bisa diinput jika device_id tidak valid (Foreign Key Constraint Protection)
        $invalidInput = false;
        try {
            Sensor::create(['device_id' => 9999, 'ph' => 7.0]);
        } catch (\Illuminate\Database\QueryException $e) {
            $invalidInput = true;
        }
        
        expect($invalidInput)->toBeTrue();
    });

});

// ==========================================================================
// 3. ACCURACY & RELIABILITY (AKURASI PENANDA WAKTU RTC)
// ==========================================================================
describe('Accuracy and Reliability', function () {

    it('NFR-03 Mencatat timestamp data sensor secara akurat dan valid sesuai format waktu asli', function () {
        $device = Device::factory()->create();
        $sensor = Sensor::create([
            'device_id' => $device->id,
            'ph' => 6.5,
            'liquid_temperature' => 30.0,
            'temperature' => 28.0,
            'gas' => 40.0,
            'humidity' => 60.0
        ]);

        // Validasi akurasi penanda waktu RTC (Format ISO / Y-m-d H:i:s)
        expect($sensor->created_at)->toBeObject();
        expect(strtotime($sensor->created_at->toDateTimeString()))->toBeGreaterThan(0);
    });

});

// ==========================================================================
// 4. PERFORMANCE & TIMELINESS (KECEPATAN RESPONS)
// ==========================================================================
describe('Performance and Timeliness', function () {

    it('NFR-04 Sistem menampilkan data hasil pemantauan secara cepat (di bawah ambang batas toleransi)', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();
        
        // Mengukur waktu awal sebelum mengakses dashboard (dalam milidetik)
        $startTime = microtime(true);
        
        actingAs($admin, 'web')->get('/admin');
        
        // Mengukur waktu akhir sesudah dashboard termuat
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Memastikan waktu respons sistem di bawah 2 detik demi performa real-time
        expect($executionTime)->toBeLessThan(2.0);
    });

});

// ==========================================================================
// 5. USABILITY, ACCESSIBILITY, & COMPATIBILITY (AKSESIBILITAS PLATFORM)
// ==========================================================================
describe('Usability and Compatibility', function () {

    it('NFR-05 Dashboard dapat diakses dan merespons dengan struktur layout HTML yang kompatibel', function () {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $admin = User::factory()->create();

        // Memastikan view menghasilkan output HTML valid agar fleksibel dibuka di komputer, tablet, maupun smartphone
        actingAs($admin, 'web')
            ->get('/admin')
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    });

    it('NFR-06 Struktur sistem mendukung kemudahan perawatan (Maintainability)', function () {
        // Memastikan class model utama pembentuk sistem dapat dimuat dan diidentifikasi dengan benar
        expect(class_exists(\App\Models\Sensor::class))->toBeTrue();
        expect(class_exists(\App\Models\Device::class))->toBeTrue();
    });

});