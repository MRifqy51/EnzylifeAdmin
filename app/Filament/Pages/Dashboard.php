<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use App\Models\Setting;  
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
        $setting = Setting::first(); // ⬅️ AMBIL SETTING

        if (!$sensor || !$setting) return [];

        $alerts = [];

        // PH
        if ($sensor->ph < $setting->ph_min || $sensor->ph > $setting->ph_max) {
            $alerts[] = "pH tidak normal ({$sensor->ph})";
        }

        // TEMPERATURE
        if ($sensor->temperature < $setting->temperature_min || $sensor->temperature > $setting->temperature_max) {
            $alerts[] = "Suhu cairan tidak normal ({$sensor->temperature}°C)";
        }

        // GAS
        if ($sensor->gas > $setting->gas_max) {
            $alerts[] = "Gas terlalu tinggi ({$sensor->gas} ppm)";
        }

        // HUMIDITY
        if ($sensor->humidity < $setting->humidity_min || $sensor->humidity > $setting->humidity_max) {
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