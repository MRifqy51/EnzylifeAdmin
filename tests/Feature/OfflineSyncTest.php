
<?php

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Pastikan pengujian tidak menggunakan database utama.
    if (
        config('database.default') !== 'sqlite' ||
        config('database.connections.sqlite.database') !== ':memory:'
    ) {
        throw new RuntimeException(
            'Pengujian FR-06 harus menggunakan SQLite in-memory.'
        );
    }

    // Hindari pemanggilan SensorObserver dan WhatsApp.
    Sensor::unsetEventDispatcher();
});

test('FR-06 menyimpan reading_id dan timestamp asli', function () {
    $device = Device::factory()->create();

    $sensor = Sensor::create([
        'device_id' => $device->id,
        'reading_id' => 'FR06-TEST-001',
        'recorded_at' => '2026-11-19 08:00:00',
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,
    ]);

    $saved = Sensor::findOrFail($sensor->id);

    expect($saved->reading_id)->toBe('FR06-TEST-001');
    expect($saved->recorded_at->format('Y-m-d H:i:s'))
        ->toBe('2026-11-19 08:00:00');
});

test('FR-06 mencegah reading_id duplikat di database', function () {
    $device = Device::factory()->create();

    $data = [
        'device_id' => $device->id,
        'reading_id' => 'FR06-DUPLICATE-001',
        'recorded_at' => '2026-11-19 08:00:00',
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,
    ];

    Sensor::create($data);

    $duplicateRejected = false;

    try {
        Sensor::create($data);
    } catch (QueryException $e) {
        $duplicateRejected = true;
    }

    expect($duplicateRejected)->toBeTrue();

    expect(
        Sensor::where('reading_id', 'FR06-DUPLICATE-001')->count()
    )->toBe(1);
});
test('FR-06 menyimpan data ketika DS18B20 terputus', function () {
    $device = Device::factory()->create();

    $processor = new \App\Services\SensorDataProcessor(
        new \App\Services\ZScoreService()
    );

    $payload = [
        'reading_id' => 'FR06-NULL-TEMP-001',
        'timestamp' => '2026-11-19 09:00:00',
        'ph' => 3.8,
        'liquid_temperature' => null,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,
    ];

    // Pastikan payload diterima validasi Laravel
    expect($processor->validateRequiredFields($payload))->toBeNull();

    // Simpan data tanpa memicu SensorObserver
    $sensor = Sensor::create([
        'device_id' => $device->id,
        'reading_id' => $payload['reading_id'],
        'recorded_at' => $payload['timestamp'],
        'ph' => $payload['ph'],
        'liquid_temperature' => $payload['liquid_temperature'],
        'temperature' => $payload['temperature'],
        'humidity' => $payload['humidity'],
        'gas' => $payload['gas'],
    ]);

    $saved = Sensor::findOrFail($sensor->id);

    expect($saved->liquid_temperature)->toBeNull();
    expect($saved->reading_id)->toBe('FR06-NULL-TEMP-001');
});