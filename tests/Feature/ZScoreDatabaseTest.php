
<?php

use App\Models\Sensor;
use App\Models\Device;
use App\Services\ZScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// TEST 1: Pengujian algoritma Z-Score
test('z-score mendeteksi anomali dari data historis database', function () {

    $service = new ZScoreService();

    // Membuat perangkat simulasi
    $device = Device::factory()->create();

    // Membuat 30 data historis pH
    
    for ($i = 0; $i < 30; $i++) {

        $nilaiPh = 3.5 + (($i % 5) * 0.02);

       
    Sensor::create([
        'device_id' => $device->id,

        // Kolom sensor yang sudah ada
        'ph' => $nilaiPh,
        'temperature' => 28.0,
        'liquid_temperature' => 27.0,
        'humidity' => 70.0,
        'gas' => 300.0,

        // Kolom tambahan Z-Score
        'ph_raw' => $nilaiPh,
        'temp_raw' => 27.0,

        // Status data historis simulasi
        'is_anomaly' => false,
    ]);

    }


        // Menguji pembacaan pH baru
        $result = $service->analyze(
            'ph_raw',
            $device->id,
            4.2
        );

        expect($result['status'])->toBe('anomaly');
        expect($result['is_anomaly'])->toBeTrue();
        expect($result['z_score'])->toBeGreaterThan(3.0);
        
        // Menguji pembacaan pH yang normal
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

// TEST 2: Memastikan database pengujian menggunakan SQLite
test('database pengujian menggunakan SQLite', function () {

    expect(config('database.default'))->toBe('sqlite');

    expect(
        config('database.connections.sqlite.database')
    )->toBe(':memory:');

});


test('data tidak valid dikeluarkan dari historis z-score', function () {

    $device = \App\Models\Device::factory()->create();

    // Simulasi pembacaan pH normal dan tidak valid
    foreach ([3.5, -2.0, 3.6] as $ph) {
        \App\Models\Sensor::create([
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

    $service = new \App\Services\ZScoreService();

    $history = $service->getHistoricalData(
        'ph_raw',
        $device->id,
        30
    );

    // Nilai pH -2 tidak boleh menjadi referensi Z-Score
    expect($history)->toBe([3.5, 3.6]);
});
