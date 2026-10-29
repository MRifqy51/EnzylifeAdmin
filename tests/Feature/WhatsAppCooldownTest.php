<?php

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Konfigurasi WhatsApp untuk pengujian.
 * Tidak mengirim pesan sungguhan.
 */
beforeEach(function () {

    // Pastikan memakai database khusus pengujian.
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

    // Threshold ini hanya untuk simulasi.
    // Bukan batas biologis yang telah divalidasi.
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

    // Nomor simulasi dari database.
    $setting->whatsapp_number = '6281234567890';

    $setting->save();
});

/**
 * Membuat pembacaan sensor dengan anomali Z-Score pH.
 */
function cooldownTestSensorData(int $deviceId): array
{
    return [
        'device_id' => $deviceId,

        'ph' => 3.5,
        'ph_raw' => 3.5,

        'temperature' => 28,
        'liquid_temperature' => 27,
        'temp_raw' => 27,

        'humidity' => 70,
        'gas' => 300,

        'z_score_ph' => 3.5,
        'z_score_temp' => 0.5,

        'is_anomaly' => true,
        'anomaly_status' => 'anomaly',

        'ph_status' => 'anomaly',
        'temp_status' => 'normal',
    ];
}

/**
 * TEST 1
 *
 * Anomali pertama harus membuat alert
 * dan satu permintaan pengiriman WhatsApp.
 */
test('anomali pertama menghasilkan satu alert', function () {

    $device = Device::factory()->create();

    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(
        Alert::where(
            'message',
            'LIKE',
            'Anomali Z-Score pH:%'
        )->count()
    )->toBe(1);

    Http::assertSentCount(1);
});

/**
 * TEST 2
 *
 * Anomali berulang dalam waktu kurang
 * dari 5 menit tidak membuat alert baru.
 */
test('anomali berulang dalam lima menit ditahan cooldown', function () {

    $device = Device::factory()->create();

    // Pembacaan pertama
    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);

    Http::assertSentCount(1);

    // Simulasi waktu maju 4 menit.
    $this->travel(4)->minutes();

    // Pembacaan kedua
    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    // Alert tidak boleh bertambah
    expect(Alert::count())->toBe(1);

    // WhatsApp tidak boleh dikirim lagi
    Http::assertSentCount(1);
});

/**
 * TEST 3
 *
 * Setelah cooldown 5 menit berakhir,
 * anomali baru boleh menghasilkan alert.
 */
test('anomali setelah cooldown menghasilkan alert baru', function () {

    $device = Device::factory()->create();

    // Pembacaan pertama
    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    expect(Alert::count())->toBe(1);

    Http::assertSentCount(1);

    // Maju 6 menit agar melewati cooldown.
    $this->travel(6)->minutes();

    // Pembacaan kedua setelah cooldown
    Sensor::create(
        cooldownTestSensorData($device->id)
    );

    // Alert baru diperbolehkan
    expect(Alert::count())->toBe(2);

    // Permintaan WhatsApp kedua diperbolehkan
    Http::assertSentCount(2);
});

/**
 * TEST 4
 *
 * Cooldown satu perangkat tidak boleh
 * menghalangi perangkat lain.
 */
test('cooldown dipisahkan berdasarkan perangkat', function () {

    $deviceA = Device::factory()->create();
    $deviceB = Device::factory()->create();

    // Anomali pada perangkat A
    Sensor::create(
        cooldownTestSensorData($deviceA->id)
    );

    expect(Alert::count())->toBe(1);

    // Anomali pada perangkat B
    Sensor::create(
        cooldownTestSensorData($deviceB->id)
    );

    // Kedua perangkat punya alert masing-masing
    expect(Alert::count())->toBe(2);

    Http::assertSentCount(2);

    // Anomali ulang pada perangkat A
    Sensor::create(
        cooldownTestSensorData($deviceA->id)
    );

    // Masih dalam cooldown perangkat A
    expect(Alert::count())->toBe(2);

    Http::assertSentCount(2);
});

/**
 * TEST 5
 *
 * Nomor WhatsApp dari database harus
 * diprioritaskan daripada nomor .env.
 */
test('notifikasi memakai nomor whatsapp dari database', function () {

    // Nomor fallback berbeda dari nomor database
    config()->set(
        'services.fonnte.admin_phone',
        '628111111111'
    );

    $setting = Setting::first();

    $setting->whatsapp_number = '628222222222';
    $setting->save();

    $device = Device::factory()->create();

    // Memicu anomali Z-Score pH
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
