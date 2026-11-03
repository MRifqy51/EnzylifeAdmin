
<?php

use App\Models\Sensor;
use App\Models\Device;
use App\Services\ZScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * TEST 1
 *
 * Memastikan algoritma Z-Score dapat mendeteksi
 * pembacaan anomali berdasarkan data historis.
 */
test('z-score mendeteksi anomali dari data historis database', function () {

    $service = new ZScoreService();

    $device = Device::factory()->create();

    // Membuat 30 data historis pH yang valid.
    for ($i = 0; $i < 30; $i++) {

        $nilaiPh = 3.5 + (($i % 5) * 0.02);

        Sensor::create([
            'device_id' => $device->id,

            'ph' => $nilaiPh,
            'ph_raw' => $nilaiPh,

            'temperature' => 28.0,
            'liquid_temperature' => 27.0,
            'temp_raw' => 27.0,

            'humidity' => 70.0,
            'gas' => 300.0,

            'ph_status' => 'normal',
            'temp_status' => 'normal',

            'is_anomaly' => false,
        ]);
    }

    // Pembacaan baru yang menyimpang.
    $result = $service->analyze(
        'ph_raw',
        $device->id,
        4.2
    );

    expect($result['status'])->toBe('anomaly');
    expect($result['is_anomaly'])->toBeTrue();
    expect($result['z_score'])->toBeGreaterThan(3.0);

    // Pembacaan baru yang masih normal.
    $normalResult = $service->analyze(
        'ph_raw',
        $device->id,
        3.54
    );

    expect($normalResult['status'])->toBe('normal');
    expect($normalResult['is_anomaly'])->toBeFalse();

    expect(abs($normalResult['z_score']))
        ->toBeLessThanOrEqual(3.0);
});


/**
 * TEST 2
 *
 * Memastikan pengujian menggunakan database
 * SQLite in-memory, bukan MySQL utama.
 */
test('database pengujian menggunakan SQLite', function () {

    expect(config('database.default'))
        ->toBe('sqlite');

    expect(
        config('database.connections.sqlite.database')
    )->toBe(':memory:');
});


/**
 * TEST 3
 *
 * Memastikan nilai sensor yang berada di luar
 * rentang fisik tidak menjadi referensi Z-Score.
 */
test('data tidak valid dikeluarkan dari historis z-score', function () {

    $device = Device::factory()->create();

    foreach ([3.5, -2.0, 3.6] as $ph) {

        Sensor::create([
            'device_id' => $device->id,

            'ph' => $ph,
            'ph_raw' => $ph,

            'liquid_temperature' => 27.0,
            'temp_raw' => 27.0,
            'temperature' => 28.0,

            'humidity' => 70.0,
            'gas' => 300.0,

            'is_anomaly' => false,
            'anomaly_status' => 'insufficient_data',
        ]);
    }

    $service = new ZScoreService();

    $history = $service->getHistoricalData(
        'ph_raw',
        $device->id,
        30
    );

    // Nilai -2 tidak boleh digunakan.
    expect($history)->toBe([3.5, 3.6]);
});


/**
 * TEST 4
 *
 * Anomali suhu cairan tidak boleh menyebabkan
 * data pH normal ikut dikeluarkan.
 */
test('anomali suhu tidak menghilangkan historis ph normal', function () {

    $device = Device::factory()->create();

    // Pembacaan pertama: semua normal.
    Sensor::create([
        'device_id' => $device->id,

        'ph' => 3.5,
        'ph_raw' => 3.5,

        'liquid_temperature' => 27.0,
        'temp_raw' => 27.0,
        'temperature' => 28.0,

        'humidity' => 70.0,
        'gas' => 300.0,

        'ph_status' => 'normal',
        'temp_status' => 'normal',

        'is_anomaly' => false,
    ]);

    // Pembacaan kedua: pH normal,
    // tetapi suhu cairan anomali.
    Sensor::create([
        'device_id' => $device->id,

        'ph' => 3.6,
        'ph_raw' => 3.6,

        'liquid_temperature' => 40.0,
        'temp_raw' => 40.0,
        'temperature' => 28.0,

        'humidity' => 70.0,
        'gas' => 300.0,

        'ph_status' => 'normal',
        'temp_status' => 'anomaly',

        'is_anomaly' => true,
    ]);

    $service = new ZScoreService();

    // Kedua data pH harus masuk riwayat.
    $phHistory = $service->getHistoricalData(
        'ph_raw',
        $device->id,
        30
    );

    expect($phHistory)->toBe([3.5, 3.6]);

    // Data suhu yang anomali harus dikeluarkan.
    $tempHistory = $service->getHistoricalData(
        'temp_raw',
        $device->id,
        30
    );

    expect($tempHistory)->toBe([27.0]);
});


/**
 * TEST 5
 *
 * Sistem harus mampu mengambil 30 data valid
 * meskipun terdapat pembacaan tidak valid.
 */
test('historis zscore mengambil 30 pembacaan valid terakhir', function () {

    $device = Device::factory()->create();

    // Membuat 30 data normal.
    for ($i = 0; $i < 30; $i++) {

        $ph = 3.5 + (($i % 5) * 0.02);

        Sensor::create([
            'device_id' => $device->id,

            'ph' => $ph,
            'ph_raw' => $ph,

            'liquid_temperature' => 27.0,
            'temp_raw' => 27.0,
            'temperature' => 28.0,

            'humidity' => 70.0,
            'gas' => 300.0,

            'ph_status' => 'normal',
            'temp_status' => 'normal',

            'is_anomaly' => false,
        ]);
    }

    // Menambahkan pembacaan yang tidak valid.
    Sensor::create([
        'device_id' => $device->id,

        'ph' => -2.0,
        'ph_raw' => -2.0,

        'liquid_temperature' => 27.0,
        'temp_raw' => 27.0,
        'temperature' => 28.0,

        'humidity' => 70.0,
        'gas' => 300.0,

        'ph_status' => 'invalid_reading',
        'temp_status' => 'normal',

        'is_anomaly' => true,
    ]);

    $service = new ZScoreService();

    $history = $service->getHistoricalData(
        'ph_raw',
        $device->id,
        30
    );

    // Tetap mendapatkan 30 data valid.
    expect($history)->toHaveCount(30);

    // Nilai -2 tidak masuk referensi.
    expect($history)->not->toContain(-2.0);

    // Referensi tetap bisa digunakan.
    $result = $service->analyze(
        'ph_raw',
        $device->id,
        4.2
    );

    expect($result['status'])->toBe('anomaly');
    expect($result['is_anomaly'])->toBeTrue();
});
