<?php

namespace App\Services;

class SensorDataProcessor
{
    public function __construct(
        private ZScoreService $zscoreService
    ) {
    }
    
    public function analyzeReadings(array $data, int $deviceId): array
    {
        // Mengambil nilai sensor dari pesan MQTT
        $phRaw = $data['ph'] ?? null;
        $tempRaw = $data['liquid_temperature'] ?? null;

        // Konversi ke tipe angka
        $phRaw = is_numeric($phRaw) ? (float) $phRaw : null;
        $tempRaw = is_numeric($tempRaw) ? (float) $tempRaw : null;

        // Memeriksa rentang pengukuran
        $phValid = $phRaw !== null
            && $this->zscoreService->isValidReading('ph_raw', $phRaw);

        $tempValid = $tempRaw !== null
            && $this->zscoreService->isValidReading('temp_raw', $tempRaw);

        // Analisis Z-Score
        $phResult = $phValid
            ? $this->zscoreService->analyze('ph_raw', $deviceId, $phRaw)
            : null;

        $tempResult = $tempValid
            ? $this->zscoreService->analyze('temp_raw', $deviceId, $tempRaw)
            : null;

        // Menentukan status tiap parameter
        $phStatus = $phRaw !== null && !$phValid
            ? 'invalid_reading'
            : ($phResult['status'] ?? 'not_analyzed');

        $tempStatus = $tempRaw !== null && !$tempValid
            ? 'invalid_reading'
            : ($tempResult['status'] ?? 'not_analyzed');

        // Menentukan status gabungan
        $isAnomaly = in_array($phStatus, ['anomaly', 'invalid_reading'], true)
            || in_array($tempStatus, ['anomaly', 'invalid_reading'], true);

        if ($phStatus === 'invalid_reading' || $tempStatus === 'invalid_reading') {
            $anomalyStatus = 'invalid_reading';
        } elseif ($isAnomaly) {
            $anomalyStatus = 'anomaly';
        } elseif ($phStatus === 'not_analyzed' || $tempStatus === 'not_analyzed') {
            $anomalyStatus = 'not_analyzed';
        } elseif ($phStatus === 'insufficient_data' || $tempStatus === 'insufficient_data') {
            $anomalyStatus = 'insufficient_data';
        } elseif ($phStatus === 'undefined_z_score' || $tempStatus === 'undefined_z_score') {
            $anomalyStatus = 'undefined_z_score';
        } else {
            $anomalyStatus = 'normal';
        }

        return [
            'ph_raw' => $phRaw,
            'temp_raw' => $tempRaw,
            'z_score_ph' => $phResult['z_score'] ?? null,
            'z_score_temp' => $tempResult['z_score'] ?? null,
            'ph_status' => $phStatus,
            'temp_status' => $tempStatus,
            'is_anomaly' => $isAnomaly,
            'anomaly_status' => $anomalyStatus,
        ];
    }
    
public function validateRequiredFields(array $data): ?string
{
    $requiredFields = [
        'ph',
        'liquid_temperature',
        'temperature',
    ];

    foreach ($requiredFields as $field) {
        if (
            !array_key_exists($field, $data) ||
            !is_numeric($data[$field]) ||
            !is_finite((float) $data[$field])
        ) {
            return $field;
        }
    }

    return null;
}

}

