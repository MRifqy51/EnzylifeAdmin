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

        // 1. Dibuat KUNCI (HARDCODE) di tanggal 11 Juli 2026
        $startDate = Carbon::parse('2026-07-11');

        // 2. Hitung progres otomatis (Target: 3 Bulan / 90 Hari)
        $totalDaysNeeded = 90; 
        
        // Menghitung selisih hari dari 11 Juli 2026 sampai HARI INI
        $daysPassed = max(0, $startDate->diffInDays(now())); 
        
        // Hitung persentase progres
        $progress = round(min(100, ($daysPassed / $totalDaysNeeded) * 100));

        return [
            'name'     => 'Fermentasi Eco Enzyme',
            'code'     => 'ECO-V-01',
            'status'   => 'AKTIF',
            'progress' => $progress, // Persentase akan otomatis dihitung dari 11 Juli

            'ph'        => $latest?->ph ?? '—',
            'air_temp'  => $latest?->temperature ?? '—',
            'liq_temp'  => $latest?->liquid_temperature ?? '—',
            'gas'       => $latest?->gas ?? '—',
            'humidity'  => $latest?->humidity ?? '—',

            'started' => $startDate->format('Y-m-d'), // Akan selalu menampilkan 2026-07-11
            'volume'  => '5L',

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