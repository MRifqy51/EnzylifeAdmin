<?php

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('data sensor dan hasil z-score tersimpan di database', function () {

    // Pastikan menggunakan database pengujian
    expect(config('database.default'))->toBe('sqlite');

    // Membuat perangkat simulasi
    $device = Device::factory()->create();

    // Menyimpan pembacaan sensor simulasi
    $sensor = Sensor::create([
        'device_id' => $device->id,

        // Data untuk dashboard lama
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,

        // Data mentah
        'ph_raw' => 3.8,
        'temp_raw' => 27.0,

        // Hasil Z-Score simulasi
        'z_score_ph' => 0.5,
        'z_score_temp' => 0.2,

        // Status hasil analisis
        'is_anomaly' => false,
        'anomaly_status' => 'normal',
    ]);

    // Ambil kembali data dari database
    $savedSensor = Sensor::findOrFail($sensor->id);

    // Periksa hasil penyimpanan
    expect((float) $savedSensor->ph_raw)->toBe(3.8);
    expect((float) $savedSensor->temp_raw)->toBe(27.0);
    expect((float) $savedSensor->z_score_ph)->toBe(0.5);
    expect((float) $savedSensor->z_score_temp)->toBe(0.2);
    expect($savedSensor->is_anomaly)->toBeFalse();
    expect($savedSensor->anomaly_status)->toBe('normal');
    }); 

    
test('data anomali tidak memicu alert biologis pH', function () {

    // Buat perangkat simulasi
    $device = Device::factory()->create();

    // Nonaktifkan pemeriksaan threshold parameter lain
    // untuk mengisolasi pengujian pH
    
    // Membuat pengaturan threshold untuk pengujian
    $setting = \App\Models\Setting::firstOrNew([]);

    // Threshold pH untuk pengujian
    $setting->ph_min = 3.0;
    $setting->ph_max = 4.0;

    // Threshold parameter lain dibuat cukup lebar
    // agar tidak memicu alert dalam skenario ini
    $setting->temperature_min = -50.0;
    $setting->temperature_max = 100.0;

    $setting->liquid_temperature_min = -50.0;
    $setting->liquid_temperature_max = 100.0;

    $setting->humidity_min = 0.0;
    $setting->humidity_max = 100.0;

    $setting->gas_min = 0.0;
    $setting->gas_max = 10000.0;

    $setting->collection_interval = 5;

    $setting->save();


    // Simulasikan pembacaan pH yang terindikasi glitch
    $sensor = Sensor::create([
        'device_id' => $device->id,
        'ph' => 8.5,
        'ph_raw' => 8.5,
        'liquid_temperature' => 27.0,
        'temp_raw' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,

        'z_score_ph' => 5.0,
        'z_score_temp' => 0.2,
        'is_anomaly' => true,
        'anomaly_status' => 'anomaly',
    ]);

    // Tidak boleh membuat alert biologis untuk pH glitch
    $biologicalAlerts = \App\Models\Alert::query()
        ->where('sensor_id', $sensor->id)
        ->where('message', 'LIKE', 'Sensor pH Level%')
        ->count();

    expect($biologicalAlerts)->toBe(0);
});


test('data valid di luar threshold memicu alert biologis pH', function () {

    // Pastikan database pengujian menggunakan SQLite
    expect(config('database.default'))->toBe('sqlite');

    $device = Device::factory()->create();

    // Pengaturan threshold simulasi
    $setting = \App\Models\Setting::firstOrNew([]);

    $setting->ph_min = 3.0;
    $setting->ph_max = 4.0;

    $setting->temperature_min = -50.0;
    $setting->temperature_max = 100.0;

    $setting->liquid_temperature_min = -50.0;
    $setting->liquid_temperature_max = 100.0;

    $setting->humidity_min = 0.0;
    $setting->humidity_max = 100.0;

    $setting->gas_min = 0.0;
    $setting->gas_max = 10000.0;

    $setting->collection_interval = 5;
    $setting->save();

    // Simulasi data yang lolos pemeriksaan Z-Score,
    // tetapi berada di luar threshold operasional pH
    $sensor = Sensor::create([
        'device_id' => $device->id,
        'ph' => 4.5,
        'ph_raw' => 4.5,
        'liquid_temperature' => 27.0,
        'temp_raw' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,       
        'z_score_ph' => 1.2,
        'z_score_temp' => 0.2,
        'is_anomaly' => false,
        'anomaly_status' => 'normal',

        // Status Z-Score masing-masing parameter
        'ph_status' => 'normal',
        'temp_status' => 'normal',

    ]);

    // Pastikan alert biologis pH dibuat
    $biologicalAlerts = \App\Models\Alert::query()
        ->where('sensor_id', $sensor->id)
        ->where('message', 'LIKE', 'Sensor pH Level%')
        ->count();

    expect($biologicalAlerts)->toBe(1);
});



