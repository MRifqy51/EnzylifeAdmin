<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dashboard extends Page
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;
    protected static ?int $navigationSort = 1;
    public array $alerts = [];

    protected string $view = 'filament.pages.dashboard';

    public function getStats(): array
    {
        $latest = Sensor::latest()->first();

        return [
            'ph' => $latest?->ph ?? 0,
            'temperature' => $latest?->temperature ?? 0,
            'gas' => $latest?->gas ?? 0,
            'humidity' => $latest?->humidity ?? 0,
        ];
    }

    public function mount(): void
{
    $this->alerts = $this->getAlerts();
}

    protected function getAlerts(): array
    {
        $sensor = Sensor::latest()->first();

        if (!$sensor) return [];

        $alerts = [];

        // sementara pakai nilai default (nanti bisa ambil dari Pengaturan)
        if ($sensor->ph < 4 || $sensor->ph > 6.5) {
            $alerts[] = "pH tidak normal ({$sensor->ph})";
        }

        if ($sensor->temperature < 20 || $sensor->temperature > 35) {
            $alerts[] = "Suhu cairan tidak normal ({$sensor->temperature}°C)";
        }

        if ($sensor->gas > 500) {
            $alerts[] = "Gas terlalu tinggi ({$sensor->gas} ppm)";
        }

        if ($sensor->humidity < 40 || $sensor->humidity > 85) {
            $alerts[] = "Kelembaban tidak normal ({$sensor->humidity}%)";
        }

        return $alerts;
    }

    public function getChartData(): array
    {
        $data = Sensor::latest()->take(10)->get()->reverse();

        return [
            'labels' => $data->pluck('created_at')->map(fn ($d) => $d->format('H:i'))->toArray(),
            'ph' => $data->pluck('ph')->toArray(),
            'temperature' => $data->pluck('temperature')->toArray(),
        ];
    }

    public function getTableData()
    {
        return Sensor::latest()->take(10)->get();
    }
}