
<?php

use App\Console\Commands\MqttListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\ConnectionSettings;

uses(RefreshDatabase::class);

test('FR-06 listener mendaftarkan callback MQTT tanpa koneksi asli', function () {

    // Pastikan tidak menggunakan database utama.
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))
        ->toBe(':memory:');

    config()->set('services.hivemq.username', 'test-user');
    config()->set('services.hivemq.password', 'test-password');

    $mqtt = Mockery::mock(MqttClientContract::class);

    $mqtt->shouldReceive('connect')
        ->once()
        ->with(Mockery::type(ConnectionSettings::class), false);

    $mqtt->shouldReceive('subscribe')
        ->once()
        ->with(
            'enzylife/device01',
            Mockery::type('callable'),
            1
        );

    $mqtt->shouldReceive('loop')
        ->once()
        ->with(true, true);

    $mqtt->shouldNotReceive('publish');

    $listener = new class($mqtt) extends MqttListener {

        public function __construct(
            private MqttClientContract $mockClient
        ) {
            parent::__construct();
        }

        protected function createClient(
            string $server,
            int $port,
            string $clientId
        ): MqttClientContract {
            return $this->mockClient;
        }
    };

   // Siapkan output console untuk pengujian.
    $listener->setLaravel(app());

    $listener->setOutput(
        new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        )
    );

    // Jalankan handler dengan MQTT Client simulasi.
    expect($listener->handle())->toBe(0);
});

test('FR-06 data MQTT baru disimpan dan mendapat ACK', function () {

    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))
        ->toBe(':memory:');

    // Menghindari SensorObserver dan WhatsApp sungguhan.
    \App\Models\Sensor::unsetEventDispatcher();

    $device = \App\Models\Device::factory()->create();

    // Listener menggunakan device_id = 1.
    expect($device->id)->toBe(1);

    config()->set('services.hivemq.username', 'test-user');
    config()->set('services.hivemq.password', 'test-password');

    $mqtt = Mockery::mock(MqttClientContract::class);

    $mqtt->shouldReceive('connect')
        ->once();

    $mqtt->shouldReceive('subscribe')
        ->once()
        ->with(
            'enzylife/device01',
            Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
                return is_callable($callback);
            }),
            1
        );

    $mqtt->shouldReceive('loop')
        ->once()
        ->with(true, true)
        ->andReturnUsing(function () use (&$capturedCallback) {

            $payload = [
                'reading_id' => 'FR06-MQTT-TEST-001',
                'timestamp' => '2026-11-19 09:00:00',
                'device_id' => 'device01',
                'sd_available' => true,
                'ph' => 3.8,
                'liquid_temperature' => 27.0,
                'temperature' => 28.0,
                'humidity' => 70.0,
                'gas' => 300.0,
            ];

            $capturedCallback(
                'enzylife/device01',
                json_encode($payload)
            );
        });

    $mqtt->shouldReceive('publish')
        ->once()
        ->with(
            'enzylife/device01/ack',
            Mockery::on(function ($payload) {
                $data = json_decode($payload, true);

                return ($data['reading_id'] ?? null)
                    === 'FR06-MQTT-TEST-001'
                    && ($data['status'] ?? null) === 'saved';
            }),
            1
        );

    $listener = new class($mqtt) extends MqttListener {

        public function __construct(
            private MqttClientContract $mockClient
        ) {
            parent::__construct();
        }

        protected function createClient(
            string $server,
            int $port,
            string $clientId
        ): MqttClientContract {
            return $this->mockClient;
        }
    };

    $listener->setLaravel(app());

    $listener->setOutput(
        new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        )
    );

    expect($listener->handle())->toBe(0);

    // Verifikasi hasil penyimpanan.
    $saved = \App\Models\Sensor::where(
        'reading_id',
        'FR06-MQTT-TEST-001'
    )->first();

    expect($saved)->not->toBeNull();

    expect($saved->recorded_at->format('Y-m-d H:i:s'))
        ->toBe('2026-11-19 09:00:00');

    expect(
        \App\Models\Sensor::where(
            'reading_id',
            'FR06-MQTT-TEST-001'
        )->count()
    )->toBe(1);
});

test('FR-06 data MQTT duplikat tidak tersimpan ulang tetapi tetap mendapat ACK', function () {

    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))
        ->toBe(':memory:');

    // Nonaktifkan observer agar tidak mengirim WhatsApp.
    \App\Models\Sensor::unsetEventDispatcher();

    $device = \App\Models\Device::factory()->create();

    expect($device->id)->toBe(1);

    // Simpan pembacaan yang sudah pernah diterima.
    \App\Models\Sensor::create([
        'device_id' => $device->id,
        'reading_id' => 'FR06-MQTT-DUP-001',
        'recorded_at' => '2026-11-19 09:00:00',
        'ph' => 3.8,
        'liquid_temperature' => 27.0,
        'temperature' => 28.0,
        'humidity' => 70.0,
        'gas' => 300.0,
    ]);

    config()->set('services.hivemq.username', 'test-user');
    config()->set('services.hivemq.password', 'test-password');

    $mqtt = Mockery::mock(MqttClientContract::class);

    $mqtt->shouldReceive('connect')->once();

    $capturedCallback = null;

    $mqtt->shouldReceive('subscribe')
        ->once()
        ->with(
            'enzylife/device01',
            Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
                return is_callable($callback);
            }),
            1
        );

    $mqtt->shouldReceive('loop')
        ->once()
        ->with(true, true)
        ->andReturnUsing(function () use (&$capturedCallback) {

            $payload = [
                'reading_id' => 'FR06-MQTT-DUP-001',
                'timestamp' => '2026-11-19 09:00:00',
                'device_id' => 'device01',
                'sd_available' => true,
                'ph' => 3.8,
                'liquid_temperature' => 27.0,
                'temperature' => 28.0,
                'humidity' => 70.0,
                'gas' => 300.0,
            ];

            $capturedCallback(
                'enzylife/device01',
                json_encode($payload)
            );
        });

    // Duplikat harus tetap menerima ACK.
    $mqtt->shouldReceive('publish')
        ->once()
        ->with(
            'enzylife/device01/ack',
            Mockery::on(function ($payload) {
                $data = json_decode($payload, true);

                return ($data['reading_id'] ?? null)
                    === 'FR06-MQTT-DUP-001'
                    && ($data['status'] ?? null) === 'saved';
            }),
            1
        );

    $listener = new class($mqtt) extends MqttListener {

        public function __construct(
            private MqttClientContract $mockClient
        ) {
            parent::__construct();
        }

        protected function createClient(
            string $server,
            int $port,
            string $clientId
        ): MqttClientContract {
            return $this->mockClient;
        }
    };

    $listener->setLaravel(app());

    $listener->setOutput(
        new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        )
    );

    expect($listener->handle())->toBe(0);

    // Jumlah data harus tetap satu.
    expect(
        \App\Models\Sensor::where(
            'reading_id',
            'FR06-MQTT-DUP-001'
        )->count()
    )->toBe(1);
});

test('FR-06 tidak mengirim ACK jika penyimpanan database gagal', function () {

    // Pastikan database pengujian terisolasi.
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))
        ->toBe(':memory:');

    // Hindari SensorObserver dan WhatsApp sungguhan.
    \App\Models\Sensor::unsetEventDispatcher();

    $device = \App\Models\Device::factory()->create();
    expect($device->id)->toBe(1);

    config()->set('services.hivemq.username', 'test-user');
    config()->set('services.hivemq.password', 'test-password');

    $mqtt = Mockery::mock(MqttClientContract::class);
    $capturedCallback = null;

    $mqtt->shouldReceive('connect')->once();

    $mqtt->shouldReceive('subscribe')
        ->once()
        ->with(
            'enzylife/device01',
            Mockery::on(function ($callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
                return is_callable($callback);
            }),
            1
        );

    // Simulasikan kegagalan INSERT dengan menghapus tabel
    // sensors di database SQLite khusus pengujian.
    $mqtt->shouldReceive('loop')
        ->once()
        ->with(true, true)
        ->andReturnUsing(function () use (&$capturedCallback) {

            \Illuminate\Support\Facades\Schema::drop('sensors');

            $payload = [
                'reading_id' => 'FR06-DB-FAIL-001',
                'timestamp' => '2026-11-19 09:00:00',
                'device_id' => 'device01',
                'sd_available' => true,
                'ph' => 3.8,
                'liquid_temperature' => 27.0,
                'temperature' => 28.0,
                'humidity' => 70.0,
                'gas' => 300.0,
            ];

            $capturedCallback(
                'enzylife/device01',
                json_encode($payload)
            );
        });

    // ACK tidak boleh dikirim saat database gagal.
    $mqtt->shouldNotReceive('publish');

    $listener = new class($mqtt) extends MqttListener {

        public function __construct(
            private MqttClientContract $mockClient
        ) {
            parent::__construct();
        }

        protected function createClient(
            string $server,
            int $port,
            string $clientId
        ): MqttClientContract {
            return $this->mockClient;
        }
    };

    $listener->setLaravel(app());

    $listener->setOutput(
        new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        )
    );

    // Listener menangani exception di dalam callback.
    expect($listener->handle())->toBe(0);
});