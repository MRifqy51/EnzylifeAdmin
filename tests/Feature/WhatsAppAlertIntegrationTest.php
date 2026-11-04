
<?php

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Konfigurasi simulasi Fonnte.
 * Tidak mengirim WhatsApp sungguhan.
 */
function setupEnzylifeWhatsAppFake(): void
{
    expect(config('database.default'))->toBe('sqlite');

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
 * Bukan batas biologis fermentasi final.
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
    $setting->whatsapp_number = '6281234567890';

    $setting->save();
}

/**
 * Data sensor simulasi.
 *
 * Status hasil Z-Score disediakan secara
 * langsung agar pengujian fokus pada Observer
 * dan pengiriman WhatsApp.
 */
function enzylifeSensorData(
    int $deviceId,
    array $overrides = []
): array {
    return array_merge([
        'device_id' => $deviceId,

        'ph' => 3.5,
        'ph_raw' => 3.5,

        'liquid_temperature' => 27.0,
        'temp_raw' => 27.0,

        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,

        'z_score_ph' => 0.5,
        'z_score_temp' => 0.5,

        'is_anomaly' => false,
        'anomaly_status' => 'normal',

        'ph_status' => 'normal',
        'temp_status' => 'normal',
    ], $overrides);
}

/**
 * TEST 1
 * Threshold maksimum pH menghasilkan danger.
 */
test('alert danger mengirim whatsapp sekali selama cooldown', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.5,
            'ph_raw' => 4.5,
        ])
    );

    expect(Alert::count())->toBe(1);
    expect(Alert::first()->type)->toBe('threshold_ph');
    expect(Alert::first()->level)->toBe('danger');

    Http::assertSentCount(1);

    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.6,
            'ph_raw' => 4.6,
        ])
    );

    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/**
 * TEST 2
 * Threshold minimum pH menghasilkan warning.
 */
test('alert warning mengirim whatsapp sekali selama cooldown', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 2.5,
            'ph_raw' => 2.5,
        ])
    );

    expect(Alert::count())->toBe(1);
    expect(Alert::first()->type)->toBe('threshold_ph');
    expect(Alert::first()->level)->toBe('warning');

    Http::assertSentCount(1);

    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 2.6,
            'ph_raw' => 2.6,
        ])
    );

    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/**
 * TEST 3
 * Anomali statistik pH tidak langsung
 * menghasilkan alert atau WhatsApp.
 */
test('anomali zscore ph tidak mengirim whatsapp', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    $sensor = Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.5,
            'ph_raw' => 4.5,
            'z_score_ph' => 4.2,
            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',
            'ph_status' => 'anomaly',
        ])
    );

    expect($sensor->ph_status)->toBe('anomaly');
    expect((float) $sensor->z_score_ph)
        ->toBeGreaterThan(3.0);

    expect(Alert::count())->toBe(0);
    Http::assertNothingSent();

    // Pembacaan anomali kedua juga tidak
    // menghasilkan peringatan fermentasi.
    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 4.6,
            'ph_raw' => 4.6,
            'z_score_ph' => 4.5,
            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',
            'ph_status' => 'anomaly',
        ])
    );

    expect(Alert::count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * TEST 4
 * Anomali statistik suhu cairan tidak
 * langsung menghasilkan alert WhatsApp.
 */
test('anomali zscore suhu cairan tidak mengirim whatsapp', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds(
        liquidMin: 20.0,
        liquidMax: 30.0
    );

    $device = Device::factory()->create();

    $sensor = Sensor::create(
        enzylifeSensorData($device->id, [
            'liquid_temperature' => 35.0,
            'temp_raw' => 35.0,
            'z_score_temp' => 4.2,
            'is_anomaly' => true,
            'anomaly_status' => 'anomaly',
            'temp_status' => 'anomaly',
        ])
    );

    expect($sensor->temp_status)->toBe('anomaly');
    expect((float) $sensor->z_score_temp)
        ->toBeGreaterThan(3.0);

    expect(Alert::count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * TEST 5
 * Histori belum mencukupi tidak memicu
 * alert threshold dalam implementasi saat ini.
 */
test('data historis belum cukup tidak mengirim alert', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    Sensor::create(
        enzylifeSensorData($device->id, [
            'z_score_ph' => null,
            'z_score_temp' => null,
            'anomaly_status' => 'insufficient_data',
            'ph_status' => 'insufficient_data',
            'temp_status' => 'insufficient_data',
        ])
    );

    expect(Alert::count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * TEST 6
 * Pembacaan di luar rentang fisik
 * tidak menghasilkan alert fermentasi.
 */
test('invalid reading tidak mengirim alert whatsapp', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    Sensor::create(
        enzylifeSensorData($device->id, [
            'ph' => 16.0,
            'ph_raw' => 16.0,
            'z_score_ph' => null,
            'is_anomaly' => true,
            'anomaly_status' => 'invalid_reading',
            'ph_status' => 'invalid_reading',
        ])
    );

    expect(Alert::count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * TEST 7
 * Z-Score yang tidak dapat dihitung
 * tidak boleh memicu alert.
 */
test('undefined zscore tidak mengirim alert', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $device = Device::factory()->create();

    Sensor::create(
        enzylifeSensorData($device->id, [
            'z_score_ph' => null,
            'z_score_temp' => null,
            'anomaly_status' => 'undefined_z_score',
            'ph_status' => 'undefined_z_score',
            'temp_status' => 'undefined_z_score',
        ])
    );

    expect(Alert::count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * TEST 8
 * Cooldown threshold berlaku per perangkat,
 * bukan secara global.
 */
test('cooldown threshold terpisah untuk setiap perangkat', function () {

    setupEnzylifeWhatsAppFake();
    setupEnzylifeTestThresholds();

    $deviceA = Device::factory()->create();
    $deviceB = Device::factory()->create();

    $thresholdData = [
        'ph' => 4.5,
        'ph_raw' => 4.5,
        'ph_status' => 'normal',
        'z_score_ph' => 0.5,
    ];

    Sensor::create(
        enzylifeSensorData($deviceA->id, $thresholdData)
    );

    Sensor::create(
        enzylifeSensorData($deviceB->id, $thresholdData)
    );

    expect(Alert::count())->toBe(2);

    expect(
        Alert::with('sensor')
            ->get()
            ->pluck('sensor.device_id')
            ->unique()
            ->count()
    )->toBe(2);

    Http::assertSentCount(2);

    // Pelanggaran ulang perangkat A
    // masih dalam cooldown.
    Sensor::create(
        enzylifeSensorData($deviceA->id, $thresholdData)
    );

    expect(Alert::count())->toBe(2);
    Http::assertSentCount(2);
});

/**
 * TEST 9
 * Anomali pH tidak menghalangi pemeriksaan
 * threshold suhu cairan yang lolos filter.
 */
test('anomali ph tidak menghalangi threshold suhu cairan', function () {

    setupEnzylifeWhatsAppFake();

    setupEnzylifeTestThresholds(
        liquidMin: 20.0,
        liquidMax: 30.0
    );

    $device = Device::factory()->create();

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

    // pH anomali tidak menjadi alert fermentasi.
    expect(
        Alert::where('type', 'zscore_ph')->count()
    )->toBe(0);

    expect(
        Alert::where('type', 'threshold_ph')->count()
    )->toBe(0);

    // Suhu yang lolos filter tetap diperiksa.
    expect(
        Alert::where(
            'type',
            'threshold_liquid_temperature'
        )->count()
    )->toBe(1);

    expect(Alert::count())->toBe(1);

    // Hanya threshold suhu memicu WhatsApp.
    Http::assertSentCount(1);
});
