<?php

use App\Models\Device;
use App\Services\SensorDataProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sensor data processor mendeteksi pembacaan tidak valid', function () {

    // Memastikan database pengujian menggunakan SQLite
    expect(config('database.default'))->toBe('sqlite');

    // Membuat perangkat simulasi
    $device = Device::factory()->create();

    // Memanggil service melalui Laravel container
    $processor = app(SensorDataProcessor::class);

    // Skenario 1: pH tidak valid
    $resultPh = $processor->analyzeReadings([
        'ph' => -2.0,
        'liquid_temperature' => 27.0,
    ], $device->id);

    expect($resultPh['ph_status'])->toBe('invalid_reading');
    expect($resultPh['anomaly_status'])->toBe('invalid_reading');
    expect($resultPh['is_anomaly'])->toBeTrue();
    expect($resultPh['z_score_ph'])->toBeNull();

    // Skenario 2: suhu cairan tidak valid
    $resultTemp = $processor->analyzeReadings([
        'ph' => 3.8,
        'liquid_temperature' => 150.0,
    ], $device->id);

    expect($resultTemp['temp_status'])->toBe('invalid_reading');
    expect($resultTemp['anomaly_status'])->toBe('invalid_reading');
    expect($resultTemp['is_anomaly'])->toBeTrue();
    expect($resultTemp['z_score_temp'])->toBeNull();

    // Skenario 3: kedua sensor valid,
    // tetapi data historis belum mencapai 30 pembacaan
    $resultWarmup = $processor->analyzeReadings([
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
    ], $device->id);

    expect($resultWarmup['ph_status'])->toBe('insufficient_data');
    expect($resultWarmup['temp_status'])->toBe('insufficient_data');
    expect($resultWarmup['anomaly_status'])->toBe('insufficient_data');
    expect($resultWarmup['is_anomaly'])->toBeFalse();
});

test('hasil sensor data processor dapat disimpan ke database', function () {

    $device = Device::factory()->create();

    $processor = app(SensorDataProcessor::class);

    // Simulasi pesan ESP32 dengan pH tidak valid
    $data = [
        'ph' => -2.0,
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,
    ];

    // Memproses data menggunakan service yang sama dengan MQTT
    $analysis = $processor->analyzeReadings($data, $device->id);

    // Menyimpan hasil analisis
    $sensor = \App\Models\Sensor::create([
        'device_id' => $device->id,
        'ph' => $analysis['ph_raw'],
        'liquid_temperature' => $analysis['temp_raw'],
        'temperature' => $data['temperature'],
        'humidity' => $data['humidity'],
        'gas' => $data['gas'],
        'ph_raw' => $analysis['ph_raw'],
        'temp_raw' => $analysis['temp_raw'],
        'z_score_ph' => $analysis['z_score_ph'],
        'z_score_temp' => $analysis['z_score_temp'],
        'is_anomaly' => $analysis['is_anomaly'],
        'anomaly_status' => $analysis['anomaly_status'],
    ]);

    // Membaca ulang hasil dari database
    $saved = \App\Models\Sensor::findOrFail($sensor->id);

    expect((float) $saved->ph_raw)->toBe(-2.0);
    expect($saved->z_score_ph)->toBeNull();
    expect($saved->is_anomaly)->toBeTrue();
    expect($saved->anomaly_status)->toBe('invalid_reading');
});

test('validasi parameter wajib pada pesan MQTT', function () {

    $processor = app(\App\Services\SensorDataProcessor::class);

    // Skenario 1: Semua parameter wajib tersedia
    $validData = [
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
    ];

    expect($processor->validateRequiredFields($validData))
        ->toBeNull();

    // Skenario 2: Temperature tidak tersedia
    $missingTemperature = [
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
    ];

    expect($processor->validateRequiredFields($missingTemperature))
        ->toBe('temperature');

    // Skenario 3: pH berisi teks, bukan angka
    $invalidPh = [
        'ph' => 'error',
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
    ];

    expect($processor->validateRequiredFields($invalidPh))
        ->toBe('ph');

    // Skenario 4: Suhu cairan tidak tersedia
    $missingLiquidTemp = [
        'ph' => 3.8,
        'temperature' => 28.0,
    ];

    expect($processor->validateRequiredFields($missingLiquidTemp))
        ->toBe('liquid_temperature');
});

