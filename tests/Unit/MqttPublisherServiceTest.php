
<?php

use App\Services\MqttPublisherService;
uses(Tests\TestCase::class);

test('interval di bawah 5 detik ditolak', function () {
    $publisher = new MqttPublisherService();

    expect(fn () => $publisher->publishInterval(4))
        ->toThrow(
            InvalidArgumentException::class,
            'Interval harus antara 5 dan 3600 detik.'
        );
});

test('interval di atas 3600 detik ditolak', function () {
    $publisher = new MqttPublisherService();

    expect(fn () => $publisher->publishInterval(3601))
        ->toThrow(
            InvalidArgumentException::class,
            'Interval harus antara 5 dan 3600 detik.'
        );
});

test('interval nol dan negatif ditolak', function () {
    $publisher = new MqttPublisherService();

    expect(fn () => $publisher->publishInterval(0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $publisher->publishInterval(-10))
        ->toThrow(InvalidArgumentException::class);
});

test('publisher mengirim interval ke topik MQTT yang benar', function () {

    // Gunakan kredensial simulasi, bukan kredensial asli.
    config()->set('services.hivemq.username', 'test-user');
    config()->set('services.hivemq.password', 'test-password');

    // Mock client MQTT agar tidak terhubung ke HiveMQ.
    $mqtt = \Mockery::mock(
        \PhpMqtt\Client\Contracts\MqttClient::class
    );

    $mqtt->shouldReceive('connect')
        ->once()
        ->with(
            \Mockery::type(
                \PhpMqtt\Client\ConnectionSettings::class
            ),
            true
        );

    // Pastikan topik, payload, QoS, dan retain benar.
    $mqtt->shouldReceive('publish')
        ->once()
        ->with(
            'enzylife/device01/cmd',
            'SET_INTERVAL:10',
            1,
            true
        );

    $mqtt->shouldReceive('loop')
        ->once()
        ->with(true, true);

    $mqtt->shouldReceive('isConnected')
        ->once()
        ->andReturn(true);

    $mqtt->shouldReceive('disconnect')
        ->once();

    // Gunakan mock sebagai pengganti MQTT client.
    $publisher = new class($mqtt)
        extends \App\Services\MqttPublisherService {

        public function __construct(
            private \PhpMqtt\Client\Contracts\MqttClient $mockClient
        ) {}

        protected function createClient():
            \PhpMqtt\Client\Contracts\MqttClient
        {
            return $this->mockClient;
        }
    };

    $publisher->publishInterval(10);
});

test('publisher menangani kegagalan koneksi MQTT', function () {

    config()->set('services.hivemq.username', 'test-user');
    config()->set('services.hivemq.password', 'test-password');

    // Simulasikan broker gagal dihubungi.
    $mqtt = \Mockery::mock(
        \PhpMqtt\Client\Contracts\MqttClient::class
    );

    $mqtt->shouldReceive('connect')
        ->once()
        ->andThrow(
            new \RuntimeException(
                'Simulasi koneksi HiveMQ gagal'
            )
        );

    // Tidak boleh melakukan publish saat gagal connect.
    $mqtt->shouldNotReceive('publish');
    $mqtt->shouldNotReceive('loop');

    // Blok finally memeriksa status koneksi.
    $mqtt->shouldReceive('isConnected')
        ->once()
        ->andReturn(false);

    $mqtt->shouldNotReceive('disconnect');

    $publisher = new class($mqtt)
        extends \App\Services\MqttPublisherService {

        public function __construct(
            private \PhpMqtt\Client\Contracts\MqttClient $mockClient
        ) {}

        protected function createClient():
            \PhpMqtt\Client\Contracts\MqttClient
        {
            return $this->mockClient;
        }
    };

    expect(fn () => $publisher->publishInterval(10))
        ->toThrow(
            \RuntimeException::class,
            'Simulasi koneksi HiveMQ gagal'
        );
});

