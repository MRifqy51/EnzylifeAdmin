<?php

namespace App\Services;

class SensorDataProcessor
{
    public function __construct(
        private ZScoreService $zscoreService
    ) {
    }

    /**
     * Memproses data sensor dan menganalisis Z-Score.
     */
    public function analyzeReadings(
        array $data,
        int $deviceId
    ): array {

        // Ambil nilai asli dari MQTT atau sumber lain.
        $phInput = $data['ph'] ?? null;
        $tempInput = $data['liquid_temperature'] ?? null;

        // Periksa apakah input tersedia.
        $phMissing = $phInput === null;
        $tempMissing = $tempInput === null;

        // Konversi hanya jika nilainya numerik
        // dan bukan Infinity atau NaN.
        $phRaw = $this->toFiniteFloat($phInput);
        $tempRaw = $this->toFiniteFloat($tempInput);

        // Validasi rentang fisik sensor.
        $phValid = $phRaw !== null
            && $this->zscoreService->isValidReading(
                'ph_raw',
                $phRaw
            );

        $tempValid = $tempRaw !== null
            && $this->zscoreService->isValidReading(
                'temp_raw',
                $tempRaw
            );

        // Analisis Z-Score hanya untuk nilai valid.
        $phResult = $phValid
            ? $this->zscoreService->analyze(
                'ph_raw',
                $deviceId,
                $phRaw
            )
            : null;

        $tempResult = $tempValid
            ? $this->zscoreService->analyze(
                'temp_raw',
                $deviceId,
                $tempRaw
            )
            : null;

        // Bedakan input kosong dan input tidak valid.
        $phStatus = $phMissing
            ? 'not_analyzed'
            : (
                !$phValid
                    ? 'invalid_reading'
                    : ($phResult['status'] ?? 'not_analyzed')
            );

        $tempStatus = $tempMissing
            ? 'not_analyzed'
            : (
                !$tempValid
                    ? 'invalid_reading'
                    : ($tempResult['status'] ?? 'not_analyzed')
            );

        // Status gabungan kedua parameter.
        $isAnomaly = in_array(
            $phStatus,
            ['anomaly', 'invalid_reading'],
            true
        ) || in_array(
            $tempStatus,
            ['anomaly', 'invalid_reading'],
            true
        );

        if (
            $phStatus === 'invalid_reading' ||
            $tempStatus === 'invalid_reading'
        ) {
            $anomalyStatus = 'invalid_reading';

        } elseif ($isAnomaly) {
            $anomalyStatus = 'anomaly';

        } elseif (
            $phStatus === 'not_analyzed' ||
            $tempStatus === 'not_analyzed'
        ) {
            $anomalyStatus = 'not_analyzed';

        } elseif (
            $phStatus === 'insufficient_data' ||
            $tempStatus === 'insufficient_data'
        ) {
            $anomalyStatus = 'insufficient_data';

        } elseif (
            $phStatus === 'undefined_z_score' ||
            $tempStatus === 'undefined_z_score'
        ) {
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

    /**
     * Mengubah nilai numerik menjadi float valid.
     *
     * Nilai nonnumerik, NaN, dan Infinity
     * tidak boleh masuk ke perhitungan Z-Score.
     */
    private function toFiniteFloat(mixed $value): ?float
    {
        if (
            !is_int($value) &&
            !is_float($value) &&
            !is_string($value)
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number)
            ? $number
            : null;
    }

    /**
     * Memvalidasi field wajib pada payload MQTT.
     *
     * Mengembalikan nama field yang tidak valid
     * atau null jika seluruh field valid.
     */
    public function validateRequiredFields(
        array $data
    ): ?string {
        $requiredFields = [
            'ph',
            'liquid_temperature',
            'temperature',
        ];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists($field, $data) ||
                $this->toFiniteFloat($data[$field]) === null
            ) {
                return $field;
            }
        }

        return null;
    }
}
