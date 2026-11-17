<?php

use App\Services\ZScoreService;

test('mendeteksi anomali pada data pH', function () {
    $service = new ZScoreService();

    // Data historis simulasi
    $history = [3.5, 3.6, 3.5, 3.7, 3.6];

    $mean = $service->mean($history);
    $stdDev = $service->standardDeviation($history);

    // Pembacaan baru yang masih dekat dengan data historis
    $normal = $service->calculate(3.6, $mean, $stdDev);

    // Pembacaan baru dengan lonjakan buatan
    $glitch = $service->calculate(4.2, $mean, $stdDev);

    expect($service->isAnomaly($normal))->toBeFalse();
    expect($service->isAnomaly($glitch))->toBeTrue();
});

test('memvalidasi rentang pembacaan sensor', function () {

    $service = new \App\Services\ZScoreService();

    // Pembacaan dalam rentang pengukuran
    expect($service->isValidReading('ph_raw', 3.8))->toBeTrue();
    expect($service->isValidReading('temp_raw', 27.5))->toBeTrue();

    // Pembacaan di luar rentang pengukuran
    expect($service->isValidReading('ph_raw', -2.0))->toBeFalse();
    expect($service->isValidReading('ph_raw', 15.0))->toBeFalse();
    expect($service->isValidReading('temp_raw', 150.0))->toBeFalse();

    // Nama parameter tidak dikenal
    expect($service->isValidReading('unknown', 25.0))->toBeFalse();
});

