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

        return [
            'name'     => 'Citrus Peel Mix',
            'code'     => 'ECO-B-007',
            'status'   => 'ACTIVE',
            'progress' => 92,

            'ph'   => $latest?->ph ?? '—',
            'temp' => $latest?->temperature ?? '—',
            'gas'  => $latest?->gas ?? '—',

            'started' => '2024-01-01',
            'volume'  => '1L',

            'radar' => [
                'pH'       => round(min(100, (($latest?->ph ?? 7) / 14) * 100)),
                'Days'     => 60,
                'Temp'     => round(min(100, (($latest?->temperature ?? 30) / 50) * 100)),
                'Orn'      => 45,
                'Progress' => 92,
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

            fputcsv($h, [
                'Timestamp',
                'pH',
                'Temperature (°C)',
                'Gas (ppm)',
                'Humidity (%)'
            ]);

            foreach ($rows as $r) {
                fputcsv($h, [
                    Carbon::parse($r->created_at)->format('M j, Y, H:i'),
                    $r->ph,
                    $r->temperature,
                    $r->gas,
                    $r->humidity,
                ]);
            }

            fclose($h);
        }, 'sensor-readings-' . now()->format('Y-m-d') . '.csv');
    }
}
