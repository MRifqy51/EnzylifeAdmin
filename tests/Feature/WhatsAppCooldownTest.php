<?php

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
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

    // Threshold simulasi, bukan batas biologis final.
    $setting = Setting::firstOrNew([]);

    $setting->ph_min = 3.0;
    $setting->ph_max = 4.0;

    $setting->temperature_min = -50;
    $setting->temperature_max = 100;

    $setting->liquid_temperature_min = -50;
    $setting->liquid_temperature_max = 100;

    $setting->humidity_min = 0;
    $setting->humidity_max = 100;

    $setting->gas_min = 0;
    $setting->gas_max = 10000;

    $setting->collection_interval = 5;
    $setting->whatsapp_number = '6281234567890';

    $setting->save();
});

/**
 * Data yang lolos pemeriksaan Z-Score,
 * tetapi melebihi threshold maksimum pH.
 */
function cooldownTestSensorData(int $deviceId): array
{
    return [
        'device_id' => $deviceId,

        'ph' => 4.5,
        'ph_raw' => 4.5,

        'temperature' => 28,
        'liquid_temperature' => 27,
        'temp_raw' => 27,

        'humidity' => 70,
        'gas' => 300,

        'z_score_ph' => 0.5,
        'z_score_temp' => 0.5,

        'is_anomaly' => false,
        'anomaly_status' => 'normal',

        'ph_status' => 'normal',
        'temp_status' => 'normal',
    ];
}

/**
 * Data pH yang kembali ke dalam threshold.
 */
function cooldownNormalSensorData(int $deviceId): array
{
    $data = cooldownTestSensorData($deviceId);

    $data['ph'] = 3.5;
    $data['ph_raw'] = 3.5;

    return $data;
}

/**
 * TEST 1
 * Threshold pertama menghasilkan alert dan WhatsApp.
 */
test('threshold pertama menghasilkan satu alert', function () {

    $device = Device::factory()->create();

    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);

    $alert = Alert::first();

    expect($alert->type)->toBe('threshold_ph');
    expect($alert->level)->toBe('danger');
    expect($alert->resolved_at)->toBeNull();

    Http::assertSentCount(1);
});

/**
 * TEST 2
 * Pelanggaran threshold berulang dalam 5 menit
 * tidak membuat alert atau WhatsApp tambahan.
 */
test('threshold berulang dalam lima menit ditahan cooldown', function () {

    $device = Device::factory()->create();

    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);

    $this->travel(4)->minutes();

    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);
    Http::assertSentCount(1);
});

/**
 * TEST 3
 * Setelah cooldown, kondisi abnormal yang masih
 * berlangsung mengirim ulang WhatsApp.
 */
test('threshold setelah cooldown mengirim ulang whatsapp tanpa alert baru', function () {

    $device = Device::factory()->create();

    $firstSensor = Sensor::create(
        cooldownTestSensorData($device->id)
    );

    $alert = Alert::first();

    expect($alert)->not->toBeNull();
    expect($alert->type)->toBe('threshold_ph');
    expect($alert->sensor_id)->toBe($firstSensor->id);
    expect($alert->resolved_at)->toBeNull();

    Http::assertSentCount(1);

    $this->travel(6)->minutes();

    $secondSensor = Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);

    $alert->refresh();

    expect($alert->sensor_id)->toBe($secondSensor->id);
    expect($alert->resolved_at)->toBeNull();

    Http::assertSentCount(2);
});

/**
 * TEST 4
 * Cooldown harus terpisah untuk setiap perangkat.
 */
test('cooldown threshold dipisahkan berdasarkan perangkat', function () {

    $deviceA = Device::factory()->create();
    $deviceB = Device::factory()->create();

    Sensor::create(
        cooldownTestSensorData($deviceA->id)
    );

    expect(Alert::count())->toBe(1);

    Sensor::create(
        cooldownTestSensorData($deviceB->id)
    );

    expect(Alert::count())->toBe(2);
    Http::assertSentCount(2);

    // Pengulangan pada perangkat A
    // masih berada dalam cooldown.
    Sensor::create(
        cooldownTestSensorData($deviceA->id)
    );

    expect(Alert::count())->toBe(2);
    Http::assertSentCount(2);
});

/**
 * TEST 5
 * Nomor WhatsApp database lebih diprioritaskan
 * daripada nomor fallback konfigurasi.
 */
test('notifikasi threshold memakai nomor whatsapp dari database', function () {

    config()->set(
        'services.fonnte.admin_phone',
        '628111111111'
    );

    $setting = Setting::first();

    $setting->whatsapp_number = '628222222222';
    $setting->save();

    $device = Device::factory()->create();

    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.fonnte.com/send'
            && $request['target'] === '628222222222';
    });

    Http::assertSentCount(1);
});

/**
 * TEST 6
 * Alert threshold selesai ketika nilai pH
 * kembali ke rentang yang ditentukan.
 */
test('alert threshold selesai ketika nilai kembali normal', function () {

    $device = Device::factory()->create();

    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    $alert = Alert::first();

    expect($alert)->not->toBeNull();
    expect($alert->type)->toBe('threshold_ph');
    expect($alert->resolved_at)->toBeNull();

    Http::assertSentCount(1);

    Sensor::create(
        cooldownNormalSensorData($device->id)
    );

    $alert->refresh();

    expect($alert->resolved_at)->not->toBeNull();
    expect(Alert::count())->toBe(1);

    Http::assertSentCount(1);
});

/**
 * TEST 7
 * Pelanggaran threshold baru setelah pemulihan
 * membuat alert baru tanpa menghapus riwayat.
 */
test('threshold baru setelah pulih menghasilkan alert baru', function () {

    $device = Device::factory()->create();

    // Kondisi pertama melebihi threshold.
    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);

    $firstAlert = Alert::first();

    expect($firstAlert->type)->toBe('threshold_ph');
    expect($firstAlert->resolved_at)->toBeNull();

    Http::assertSentCount(1);

    // Kondisi kembali normal.
    Sensor::create(
        cooldownNormalSensorData($device->id)
    );

    $firstAlert->refresh();

    expect($firstAlert->resolved_at)->not->toBeNull();
    expect(Alert::count())->toBe(1);

    // Nilai kembali melebihi threshold.
    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(2);

    expect(
        Alert::whereNotNull('resolved_at')->count()
    )->toBe(1);

    expect(
        Alert::whereNull('resolved_at')->count()
    )->toBe(1);

    Http::assertSentCount(2);
});

/**
 * TEST 8
 * Anomali statistik tidak langsung memicu
 * peringatan kondisi fermentasi.
 */
test('anomali zscore tidak langsung mengirim whatsapp', function () {

    $device = Device::factory()->create();

    $data = cooldownTestSensorData($device->id);

    $data['z_score_ph'] = 4.5;
    $data['ph_status'] = 'anomaly';
    $data['is_anomaly'] = true;
    $data['anomaly_status'] = 'anomaly';

    Sensor::create($data);

    // Hasil Z-Score tetap dicatat.
    $sensor = Sensor::first();

    expect($sensor->ph_status)->toBe('anomaly');

    // Tidak membuat alert kondisi fermentasi.
    expect(Alert::count())->toBe(0);

    // Tidak mengirim WhatsApp.
    Http::assertSentCount(0);
});
