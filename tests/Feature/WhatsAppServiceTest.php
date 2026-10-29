<?php

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;

test('whatsapp gateway berhasil mengirim permintaan', function () {

    // Token simulasi khusus pengujian
    config()->set('services.fonnte.token', 'test-token');

    // Simulasi respons berhasil dari Fonnte
    Http::fake([
        'api.fonnte.com/send' => Http::response([
            'status' => true,
            'reason' => 'success',
        ], 200),
    ]);

    $service = new WhatsAppService();

    $result = $service->sendMessage(
        '6281234567890',
        'Peringatan EnzyLife: pH di luar batas aman.'
    );

    expect($result)->toBeTrue();

    Http::assertSent(fn ($request) =>
        $request->url() === 'https://api.fonnte.com/send'
        && $request->hasHeader('Authorization', 'test-token')
        && $request['target'] === '6281234567890'
        && $request['message'] === 'Peringatan EnzyLife: pH di luar batas aman.'
    );
});

test('whatsapp gateway menangani penolakan API', function () {

    config()->set('services.fonnte.token', 'test-token');

    // Simulasi API menolak permintaan
    Http::fake([
        'api.fonnte.com/send' => Http::response([
            'status' => false,
            'reason' => 'Invalid token',
        ], 200),
    ]);

    $service = new WhatsAppService();

    $result = $service->sendMessage(
        '6281234567890',
        'Pesan pengujian EnzyLife'
    );

    expect($result)->toBeFalse();
});

test('whatsapp gateway menolak konfigurasi tanpa token', function () {

    config()->set('services.fonnte.token', null);

    // Mencegah permintaan jaringan sungguhan
    Http::preventStrayRequests();
    Http::fake();

    $service = new WhatsAppService();

    $result = $service->sendMessage(
        '6281234567890',
        'Pesan pengujian EnzyLife'
    );

    expect($result)->toBeFalse();

    // Tidak boleh ada permintaan API tanpa token
    Http::assertNothingSent();
});
