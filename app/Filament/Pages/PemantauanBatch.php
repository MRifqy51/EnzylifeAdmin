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
        return 'Track and manage all active fermentation batches';
    }

    // 🔥 Batch card
    public function getBatchData(): array
    {
        $latest = Sensor::latest('created_at')->first();

        // Cari tahu tanggal pertama kali data masuk sebagai tanggal mulai fermentasi
        $firstRecord = Sensor::oldest('created_at')->first();
        $startedDate = $firstRecord ? $firstRecord->created_at->format('Y-m-d') : '2026-06-11';

        return [
            'name'     => 'Market Veggie Blend', // Diubah dari Citrus Peel
            'code'     => 'ECO-V-01',          // Kode riset pertama
            'status'   => 'ACTIVE',
            'progress' => 12,                  // Progress disesuaikan awal fermentasi

            'ph'        => $latest?->ph ?? '—',
            'air_temp'  => $latest?->temperature ?? '—',
            'liq_temp'  => $latest?->liquid_temperature ?? '—',
            'gas'       => $latest?->gas ?? '—',
            'humidity'  => $latest?->humidity ?? '—',

            'started' => $startedDate,
            'volume'  => '5L',                 // Menggunakan galon 5 Liter

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

            // 1. Tambah header kolom biar ada Air Temp dan Liquid Temp pisah
            fputcsv($h, [
                'Timestamp',
                'pH Level',
                'Air Temp (°C)',
                'Liquid Temp (°C)',
                'Gas (ppm)',
                'Humidity (%)'
            ]);

            // 2. Masukkan data dari database sesuai urutan header di atas
            foreach ($rows as $r) {
                fputcsv($h, [
                    Carbon::parse($r->created_at)->format('M j, Y, H:i'),
                    $r->ph,
                    $r->temperature,          // Ini Air Temp
                    $r->liquid_temperature,   // Ini Liquid Temp (Baru ditambahkan)
                    $r->gas,
                    $r->humidity,
                ]);
            }

            fclose($h);
        }, 'sensor-readings-' . now()->format('Y-m-d') . '.csv');
    }
}
