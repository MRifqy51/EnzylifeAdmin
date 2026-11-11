
<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Models\Device;
use App\Models\Sensor;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// ==========================================================================
// 1. RELIABILITY & AVAILABILITY
// ==========================================================================
describe('Reliability and Availability', function () {

    it('NFR-01 Sistem tetap berjalan stabil dan mencatat data tanpa gangguan interupsi null data', function () {

        $device = Device::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            Sensor::create([
                'device_id' => $device->id,
                'ph' => 6.5,
                'liquid_temperature' => 30.0,
                'temperature' => 28.0,
                'gas' => 40.0,
                'humidity' => 60.0,
            ]);
        }

        expect(
            Sensor::where('device_id', $device->id)->count()
        )->toBe(10);
    });

});

// ==========================================================================
// 2. SECURITY & DATA INTEGRITY
// ==========================================================================
describe('Security and Data Integrity', function () {

    it('NFR-02 Memastikan penyimpanan aman di database lokal/cloud dan terikat integritas foreign key', function () {

        expect(
            Schema::hasColumns(
                'sensors',
                ['device_id', 'ph', 'gas']
            )
        )->toBeTrue();

        $invalidInput = false;

        try {
            Sensor::create([
                'device_id' => 9999,
                'ph' => 7.0,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $invalidInput = true;
        }

        expect($invalidInput)->toBeTrue();
    });

});

// ==========================================================================
// 3. ACCURACY & RELIABILITY
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
            'humidity' => 60.0,
        ]);

        expect($sensor->created_at)->toBeObject();

        expect(
            strtotime($sensor->created_at->toDateTimeString())
        )->toBeGreaterThan(0);
    });

});

// ==========================================================================
// 4. PERFORMANCE & TIMELINESS
// ==========================================================================
describe('Performance and Timeliness', function () {

    it('NFR-04 Sistem menampilkan data hasil pemantauan secara cepat (di bawah ambang batas toleransi)', function () {

        // Buat pengguna pengujian.
        $admin = User::factory()->create();

        // Gunakan panel admin Filament.
        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        // Login dilakukan sebelum pengukuran.
        actingAs($admin, 'web');

        // Gunakan URL halaman Dashboard yang terdaftar.
        $dashboardUrl = Dashboard::getUrl(
            panel: 'admin'
        );

        // Warm-up: memuat dashboard terlebih dahulu.
        // Permintaan ini tidak masuk ke pengukuran.
        $warmupResponse = $this->get($dashboardUrl);

        // Pastikan halaman benar-benar berhasil dibuka,
        // bukan redirect ke login atau halaman lain.
        $warmupResponse->assertOk();

        // Lakukan tiga pengukuran respons.
        $responseTimes = [];

        for ($i = 0; $i < 3; $i++) {

            $startTime = microtime(true);

            $response = $this->get($dashboardUrl);

            $executionTime = microtime(true) - $startTime;

            // Jangan menerima redirect/error sebagai keberhasilan.
            $response->assertOk();

            $responseTimes[] = $executionTime;
        }

        // Median digunakan untuk mengurangi pengaruh
        // satu lonjakan waktu yang bersifat sementara.
        sort($responseTimes);

        $medianResponseTime = $responseTimes[1];

        // Persyaratan performa tetap di bawah 2 detik.
        expect($medianResponseTime)->toBeLessThan(2.0);
    });

});

// ==========================================================================
// 5. USABILITY, ACCESSIBILITY & COMPATIBILITY
// ==========================================================================
describe('Usability and Compatibility', function () {

    it('NFR-05 Dashboard dapat diakses dan merespons dengan struktur layout HTML yang kompatibel', function () {

        $admin = User::factory()->create();

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        actingAs($admin, 'web');

        $dashboardUrl = Dashboard::getUrl(
            panel: 'admin'
        );

        $response = $this->get($dashboardUrl);

        // Memastikan halaman Dashboard benar-benar terbuka.
        $response->assertOk();

        // Memastikan respons berbentuk HTML.
        $response->assertHeader(
            'Content-Type',
            'text/html; charset=UTF-8'
        );
    });

    it('NFR-06 Struktur sistem mendukung kemudahan perawatan (Maintainability)', function () {

        expect(
            class_exists(\App\Models\Sensor::class)
        )->toBeTrue();

        expect(
            class_exists(\App\Models\Device::class)
        )->toBeTrue();
    });

});
