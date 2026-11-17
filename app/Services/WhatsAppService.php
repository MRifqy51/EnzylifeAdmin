<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function sendMessage(string $phone, string $message): bool
    {
        $token = config('services.fonnte.token');

        // Pastikan konfigurasi tersedia
        if (empty($token) || empty($phone) || empty($message)) {
            Log::warning('WhatsApp configuration is incomplete.');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])
                ->asForm()
                ->timeout(10)
                ->post('https://api.fonnte.com/send', [
                    'target' => $phone,
                    'message' => $message,
                ]);

            $result = $response->json();

            // Fonnte dapat mengembalikan HTTP 200
            // meskipun permintaan ditolak oleh API.
            $success = $response->successful()
                && is_array($result)
                && ($result['status'] ?? false) === true;

            if (!$success) {
                Log::warning('WhatsApp API request failed.', [
                    'http_status' => $response->status(),
                    'api_status' => $result['status'] ?? null,
                    'reason' => $result['reason'] ?? null,
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            Log::error('WhatsApp API connection failed.', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
