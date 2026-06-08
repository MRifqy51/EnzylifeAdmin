<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use App\Models\Setting;
use App\Models\Alert;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dashboard extends Page
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;
    protected static ?int $navigationSort = 1;

    public array $alerts = [];
    public array $currentStats = []; // Menyimpan data statistik real-time

    protected string $view = 'filament.pages.dashboard';

    public function mount(): void
    {
        $this->alerts = $this->getAlerts();
        $this->currentStats = $this->getStats();
    }

    /**
     * Fungsi ini akan dipanggil secara berkala oleh Livewire Polling
     * untuk memperbarui grafik dan alert tanpa reload halaman.
     */
    public function updateData(): void
    {
        $this->alerts = $this->getAlerts();
        $this->currentStats = $this->getStats();
        
        // Memaksa browser memicu fungsi gambar ulang grafik Chart.js
        $this->dispatch('refreshChart', data: $this->getChartData());
    }

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

    protected function getAlerts(): array
    {
        return Alert::with('sensor')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($alert) {
                return [
                    'message' => $alert->message,
                    'level' => $alert->level,
                    'time' => $alert->created_at->format('H:i'),
                    'sensor_id' => $alert->sensor_id,
                ];
            })
            ->toArray();
    }

    public function getChartData(): array
    {
        $data = Sensor::latest()->take(10)->get()->reverse();

        return [
            'labels' => $data->pluck('created_at')->map(fn ($d) => $d->format('H:i'))->toArray(),
            'ph' => $data->pluck('ph')->toArray(),
            'temperature' => $data->pluck('temperature')->toArray(),
            'gas' => $data->pluck('gas')->toArray(),
            'humidity' => $data->pluck('humidity')->toArray(),
        ];
    }

    public function getTableData()
    {
        return Sensor::latest()->take(10)->get();
    }
}