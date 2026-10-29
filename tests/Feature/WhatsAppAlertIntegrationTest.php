
<?php

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Konfigurasi simulasi WhatsApp Fonnte.
 * Tidak melakukan pengiriman pesan sungguhan.
 */
function setupEnzylifeWhatsAppFake(): void
{
    config()->set('services.fonnte.token', 'test-token');
    config()->set(
        'services.fonnte.admin_phone',
        '6281234567890'
    );

    Http::preventStrayRequests();

    Http::fake([
        'https://api.fonnte.com/send' => Http::response([
            'status' => true,
            'reason' => 'success',
        ], 200),
    ]);
}

/**
 * Threshold khusus pengujian.
 * Bukan nilai biologis fermentasi yang telah divalidasi.
 */
function setupEnzylifeTestThresholds(
    float $liquidMin = -50.0,
    float $liquidMax = 100.0
): void {
    $setting = Setting::firstOrNew([]);

    $setting->ph_min = 3.0;
    $setting->ph_max = 4.0;

    $setting->temperature_min = -50.0;
    $setting->temperature_max = 100.0;

    $setting->liquid_temperature_min = $liquidMin;
    $setting->liquid_temperature_max = $liquidMax;

    $setting->humidity_min = 0.0;
    $setting->humidity_max = 100.0;

    $setting->gas_min = 0.0;
    $setting->gas_max = 10000.0;

    $setting->collection_interval = 5;

    $setting->save();
}

/**
 * Data sensor dasar untuk simulasi.
 *
 * Status Z-Score disimpan per parameter:
 * - ph_status
 * - temp_status
 *
 * Nilai dapat diganti melalui $overrides.
 */
function enzylifeSensorData(
    int $deviceId,
    array $overrides = []
): array {
    return array_merge([
        'device_id' => $deviceId,

        // Pembacaan sensor
        'ph' => 3.5,
        'ph_raw' => 3.5,

        'liquid_temperature' => 27.0,
        'temp_raw' => 27.0,

        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,

        // Hasil Z-Score simulasi
        'z_score_ph' => 0.5,
        'z_score_temp' => 0.5,

        // Status gabungan
        'is_anomaly' => false,
        'anomaly_status' => 'normal',

        // Status masing-masing parameter
        'ph_status' => 'normal',
        'temp_status' => 'normal',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| TEST 1 - ALERT DANGER
|--------------------------------------------------------------------------
*/

test('alert danger mengirim whatsapp sekali selama cooldown', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    // pH melewati threshold maksimum 4.0
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.5,
            'ph_raw' => 4.5,
        ])
    );

    expect(Alert::count())->toBe(1);
    expect(Alert::first()->level)->toBe('danger');

    Http::assertSentCount(1);

    // Kondisi danger yang sama masih dalam 5 menit
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.6,
            'ph_raw' => 4.6,
        ])
    );

    // Cooldown mencegah alert tambahan
    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| TEST 2 - ALERT WARNING
|--------------------------------------------------------------------------
*/

test('alert warning mengirim whatsapp sekali selama cooldown', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    // pH berada di bawah threshold minimum 3.0
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 2.5,
            'ph_raw' => 2.5,
        ])
    );

    expect(Alert::count())->toBe(1);
    expect(Alert::first()->level)->toBe('warning');

    Http::assertSentCount(1);

    // Kondisi warning berulang selama cooldown
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 2.6,
            'ph_raw' => 2.6,
        ])
    );

    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| TEST 3 - ANOMALI Z-SCORE PH
|--------------------------------------------------------------------------
*/

test('anomali zscore ph mengirim whatsapp sekali selama cooldown', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    $device = Device::factory()->create();

    // Simulasi pH dengan Z-Score > 3
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.5,
            'ph_raw' => 4.5,

            'z_score_ph' => 4.2,
            'z_score_temp' => 0.5,

            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',

            'ph_status' => 'anomaly',
            'temp_status' => 'normal',
        ])
    );

    expect(Alert::count())->toBe(1);

    expect(Alert::first()->level)
        ->toBe('danger');

    expect(Alert::first()->message)
        ->toContain('Anomali Z-Score pH');

    Http::assertSentCount(1);

    // Anomali pH kembali terjadi
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.6,
            'ph_raw' => 4.6,

            'z_score_ph' => 4.5,
            'z_score_temp' => 0.4,

            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',

            'ph_status' => 'anomaly',
            'temp_status' => 'normal',
        ])
    );

    // Tidak boleh membuat alert berulang
    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| TEST 4 - ANOMALI Z-SCORE SUHU CAIRAN
|--------------------------------------------------------------------------
*/

test('anomali zscore suhu cairan mengirim whatsapp sekali selama cooldown', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    $device = Device::factory()->create();

    // Simulasi DS18B20 dengan Z-Score > 3
    Sensor::create(
        enzylifeSensorData($device->id, [
            'liquid_temperature' => 35.0,
            'temp_raw' => 35.0,

            'z_score_ph' => 0.5,
            'z_score_temp' => 4.2,

            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',

            'ph_status' => 'normal',
            'temp_status' => 'anomaly',
        ])
    );

    expect(Alert::count())->toBe(1);

    expect(Alert::first()->level)
        ->toBe('danger');

    expect(Alert::first()->message)
        ->toContain('Anomali Z-Score Suhu Cairan');

    Http::assertSentCount(1);

    // Anomali suhu cairan terjadi kembali
    Sensor::create(
        enzylifeSensorData($device->id, [
            'liquid_temperature' => 36.0,
            'temp_raw' => 36.0,

            'z_score_ph' => 0.4,
            'z_score_temp' => 4.5,

            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',

            'ph_status' => 'normal',
            'temp_status' => 'anomaly',
        ])
    );

    // Cooldown mencegah duplikasi
    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/*
|--------------------------------------------------------------------------
| TEST 5 - INSUFFICIENT DATA
|--------------------------------------------------------------------------
*/

test('data historis belum cukup tidak mengirim alert anomali', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    $device = Device::factory()->create();

    // Simulasi data historis belum mencukupi
    Sensor::create(
        enzylifeSensorData($device->id, [
            'z_score_ph' => null,
            'z_score_temp' => null,

            'is_anomaly' => false,
            'anomaly_status' => 'insufficient_data',

            'ph_status' => 'insufficient_data',
            'temp_status' => 'insufficient_data',
        ])
    );

    // Tidak boleh dianggap sebagai anomali
    expect(
        Alert::where(
            'message',
            'LIKE',
            'Anomali Z-Score%'
        )->count()
    )->toBe(0);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| TEST 6 - INVALID READING
|--------------------------------------------------------------------------
*/

test('invalid reading tidak mengirim alert anomali zscore', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    $device = Device::factory()->create();

    // pH 16 berada di luar rentang fisik 0-14
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 16.0,
            'ph_raw' => 16.0,

            'z_score_ph' => null,
            'z_score_temp' => 0.5,

            'is_anomaly' => true,
            'anomaly_status' => 'invalid_reading',

            'ph_status' => 'invalid_reading',
            'temp_status' => 'normal',
        ])
    );

    // Pembacaan tidak valid bukan anomali Z-Score
    expect(
        Alert::where(
            'message',
            'LIKE',
            'Anomali Z-Score%'
        )->count()
    )->toBe(0);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| TEST 7 - UNDEFINED Z-SCORE
|--------------------------------------------------------------------------
*/

test('undefined zscore tidak mengirim alert anomali', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    $device = Device::factory()->create();

    // Simulasi Z-Score yang tidak dapat dihitung
    Sensor::create(
        enzylifeSensorData($device->id, [
            'z_score_ph' => null,
            'z_score_temp' => null,

            'is_anomaly' => false,
            'anomaly_status' => 'undefined_z_score',

            'ph_status' => 'undefined_z_score',
            'temp_status' => 'undefined_z_score',
        ])
    );

    expect(
        Alert::where(
            'message',
            'LIKE',
            'Anomali Z-Score%'
        )->count()
    )->toBe(0);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| TEST 8 - COOLDOWN BERDASARKAN PERANGKAT
|--------------------------------------------------------------------------
*/

test('cooldown anomali zscore terpisah untuk setiap perangkat', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    // Dua perangkat berbeda
    $deviceA = Device::factory()->create();
    $deviceB = Device::factory()->create();

    $anomalyData = [
        'ph' => 4.5,
        'ph_raw' => 4.5,

        'z_score_ph' => 4.2,
        'z_score_temp' => 0.5,

        'is_anomaly' => true,
        'anomaly_status' => 'anomaly',

        'ph_status' => 'anomaly',
        'temp_status' => 'normal',
    ];

    // Anomali dari perangkat A
    Sensor::create(
        enzylifeSensorData(
            $deviceA->id,
            $anomalyData
        )
    );

    // Anomali dari perangkat B
    Sensor::create(
        enzylifeSensorData(
            $deviceB->id,
            $anomalyData
        )
    );

    // Masing-masing perangkat membuat alert
    expect(Alert::count())->toBe(2);

    expect(
        Alert::with('sensor')
            ->get()
            ->pluck('sensor.device_id')
            ->unique()
            ->count()
    )->toBe(2);

    Http::assertSentCount(2);

    // Anomali berulang dari perangkat A
    Sensor::create(
        enzylifeSensorData(
            $deviceA->id,
            $anomalyData
        )
    );

    // Cooldown hanya berlaku untuk perangkat A
    expect(Alert::count())->toBe(2);

    Http::assertSentCount(2);
});

/*
|--------------------------------------------------------------------------
| TEST 9 - INDEPENDENSI PH DAN SUHU CAIRAN
|--------------------------------------------------------------------------
*/

test('anomali ph tidak menghalangi threshold suhu cairan', function () {

    expect(config('database.default'))->toBe('sqlite');

    setupEnzylifeWhatsAppFake();

    // Threshold suhu cairan maksimum 30 derajat
    setupEnzylifeTestThresholds(
        liquidMin: 20.0,
        liquidMax: 30.0
    );

    $device = Device::factory()->create();

    // pH mengalami anomali statistik.
    // Suhu cairan normal secara Z-Score,
    // tetapi melewati threshold operasional.
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.5,
            'ph_raw' => 4.5,

            'liquid_temperature' => 35.0,
            'temp_raw' => 35.0,

            'z_score_ph' => 4.2,
            'z_score_temp' => 0.5,

            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',

            'ph_status' => 'anomaly',
            'temp_status' => 'normal',
        ])
    );

    // Alert anomali pH
    expect(
        Alert::where(
            'message',
            'LIKE',
            'Anomali Z-Score pH:%'
        )->count()
    )->toBe(1);

    // Alert threshold suhu cairan
    expect(
        Alert::where(
            'message',
            'LIKE',
            'Sensor Liquid Temperature%'
        )->count()
    )->toBe(1);

    // Total dua alert
    expect(Alert::count())->toBe(2);

    // Masing-masing menghasilkan permintaan WhatsApp
    Http::assertSentCount(2);
});
