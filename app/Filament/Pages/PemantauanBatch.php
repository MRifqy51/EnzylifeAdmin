<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class PemantauanBatch extends Page
{
    protected static ?string $navigationLabel = 'Pemantauan Batch';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.pemantauan-batch';

    public ?string $startDate = null;
    public ?string $endDate   = null;
    public int $perPage = 50;

    public function getTitle(): string
    {
        return 'Pemantauan Batch';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    // 🔥 Batch card
    public function getBatchData(): array
    {
        $latest = Sensor::latest('created_at')->first();

        // 1. Tentukan tanggal mulai fermentasi (Default: 11 Juli 2026 atau data sensor paling awal)
        $firstRecord = Sensor::oldest('created_at')->first();
        $startDate = $firstRecord ? Carbon::parse($firstRecord->created_at) : Carbon::parse('2026-07-11');

        // 2. Hitung progres otomatis (Target: 3 Bulan / 90 Hari)
        $totalDaysNeeded = 90; 
        $daysPassed = max(0, $startDate->diffInDays(now())); // Menghitung selisih hari dari tanggal mulai ke hari ini
        
        $progress = round(min(100, ($daysPassed / $totalDaysNeeded) * 100));

        return [
            'name'     => 'Fermentasi Eco Enzyme', // Diubah dari Market Veggie Blend
            'code'     => 'ECO-V-01',              // Kode batch
            'status'   => 'AKTIF',
            'progress' => $progress,               // Progres otomatis dinamis %

            'ph'        => $latest?->ph ?? '—',
            'air_temp'  => $latest?->temperature ?? '—',
            'liq_temp'  => $latest?->liquid_temperature ?? '—',
            'gas'       => $latest?->gas ?? '—',
            'humidity'  => $latest?->humidity ?? '—',

            'started' => $startDate->format('Y-m-d'),
            'volume'  => '5L',                     // Menggunakan wadah 5 Liter

            'radar' => [
                'pH'       => round(min(100, (($latest?->ph ?? 7) / 14) * 100)),
                'Air Temp' => round(min(100, (($latest?->temperature ?? 25) / 50) * 100)),
                'Liq Temp' => round(min(100, (($latest?->liquid_temperature ?? 25) / 50) * 100)),
                'Gas'      => round(min(100, (($latest?->gas ?? 400) / 2000) * 100)),
                'Humidity' => round($latest?->humidity ?? 50),
            ],
        ];
    }

    // 🔥 Sensor table
    public function getSensorReadings(): Collection
    {
        $q = Sensor::query()->latest('created_at');

        if ($this->startDate) {
            $q->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $q->whereDate('created_at', '<=', $this->endDate);
        }

        return $q->take($this->perPage)->get();
    }

    public function getTotalCount(): int
    {
        $q = Sensor::query();

        if ($this->startDate) {
            $q->whereDate('created_at', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $q->whereDate('created_at', '<=', $this->endDate);
        }

        return $q->count();
    }

    public function applyFilter(): void
    {
        // Livewire auto refresh
    }

    // 🔥 Export CSV
    public function exportCsv(): StreamedResponse
    {
        $rows = $this->getSensorReadings();

        return response()->streamDownload(function () use ($rows) {
            $h = fopen('php://output', 'w');

            // Header kolom CSV Bahasa Indonesia
            fputcsv($h, [
                'Waktu (Timestamp)',
                'Tingkat pH',
                'Suhu Udara (°C)',
                'Suhu Cairan (°C)',
                'Gas (ppm)',
                'Kelembapan (%)'
            ]);

            // Data sensor
            foreach ($rows as $r) {
                fputcsv($h, [
                    Carbon::parse($r->created_at)->format('d M Y, H:i'),
                    $r->ph,
                    $r->temperature,          
                    $r->liquid_temperature,   
                    $r->gas,
                    $r->humidity,
                ]);
            }

            fclose($h);
        }, 'log-sensor-eco-enzyme-' . now()->format('Y-m-d') . '.csv');
    }
}