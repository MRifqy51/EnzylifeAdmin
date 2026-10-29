<?php

namespace App\Services;

use App\Models\Sensor;

class ZScoreService
{
    // Menghitung nilai Z-Score
    public function calculate(
        float $value,
        float $mean,
        float $stdDev
    ): ?float {
        if ($stdDev <= 0) {
            return null;
        }

        return ($value - $mean) / $stdDev;
    }

    // Memeriksa apakah Z-Score melewati threshold
    public function isAnomaly(
        ?float $zScore,
        float $threshold = 3.0
    ): bool {
        if ($zScore === null) {
            return false;
        }

        return abs($zScore) > $threshold;
    }

    // Menghitung rata-rata data historis
    public function mean(array $data): ?float
    {
        if (count($data) === 0) {
            return null;
        }

        return array_sum($data) / count($data);
    }

    // Menghitung standar deviasi sampel
    public function standardDeviation(array $data): ?float
    {
        $count = count($data);

        if ($count < 2) {
            return null;
        }

        $mean = $this->mean($data);
        $sum = 0;

        foreach ($data as $value) {
            $sum += pow($value - $mean, 2);
        }

        return sqrt($sum / ($count - 1));
    }

    // Mengambil data historis sensor dari database
    public function getHistoricalData(
        string $parameter,
        int $deviceId,
        int $windowSize = 30
    ): array {
        $allowedParameters = [
            'ph_raw',
            'temp_raw',
        ];

        if (!in_array($parameter, $allowedParameters, true)) {
            throw new \InvalidArgumentException(
                'Parameter sensor tidak valid.'
            );
        }

        if ($windowSize < 2) {
            throw new \InvalidArgumentException(
                'Window size minimal 2 data.'
            );
        }

        return Sensor::query()
            ->where('device_id', $deviceId)
            ->where('is_anomaly', false)
            ->whereNotNull($parameter)
            ->orderByDesc('id')
            ->limit($windowSize)
            ->pluck($parameter)
            ->reverse()
            ->map(fn($value) => (float) $value)
            ->filter(fn($value) => $this->isValidReading($parameter, $value))
            ->values()
            ->all();
    }

    // Menganalisis pembacaan sensor terbaru
    public function analyze(
        string $parameter,
        int $deviceId,
        float $newValue,
        int $windowSize = 30,
        float $threshold = 3.0
    ): array {
        $history = $this->getHistoricalData(
            $parameter,
            $deviceId,
            $windowSize
        );

        // Data historis belum mencukupi
        if (count($history) < $windowSize) {
            return [
                'z_score' => null,
                'is_anomaly' => false,
                'status' => 'insufficient_data',
            ];
        }

        $mean = $this->mean($history);
        $stdDev = $this->standardDeviation($history);

        $zScore = $this->calculate(
            $newValue,
            $mean,
            $stdDev
        );

        // Z-Score tidak dapat dihitung
        if ($zScore === null) {
            return [
                'z_score' => null,
                'is_anomaly' => false,
                'status' => 'undefined_z_score',
            ];
        }

        $isAnomaly = $this->isAnomaly(
            $zScore,
            $threshold
        );

        return [
            'z_score' => $zScore,
            'is_anomaly' => $isAnomaly,
            'status' => $isAnomaly ? 'anomaly' : 'normal',
        ];
        
    }
    
    // Memeriksa rentang fisik pembacaan sensor
    public function isValidReading(
        string $parameter,
        float $value
    ): bool {
        if (!is_finite($value)) {
            return false;
        }

        if ($parameter === 'ph_raw') {
            return $value >= 0 && $value <= 14;
        }

        if ($parameter === 'temp_raw') {
            return $value >= -55 && $value <= 125;
        }

        return false;
    }

}
