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
    public array $currentStats = []; 
    public array $recentLogs = []; // Tambahan untuk menampung data tabel secara reaktif

    protected string $view = 'filament.pages.dashboard';

    public function mount(): void
    {
        $this->updateDataState();
    }

    /**
     * Memperbarui seluruh state data dashboard
     */
    protected function updateDataState(): void
    {
        $latest = Sensor::latest()->first();

        $this->currentStats = [
            'ph'                 => $latest?->ph ?? 0,
            'temperature'        => $latest?->temperature ?? 0,
            'liquid_temperature' => $latest?->liquid_temperature ?? 0,
            'gas'                => $latest?->gas ?? 0,
            'humidity'           => $latest?->humidity ?? 0,
            'status'             => $latest?->status ?? 'optimal',
        ];

        // 🔥 Hanya tampilkan alert yang belum resolved
        $this->alerts = Alert::whereNull('resolved_at')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($alert) {
                return [
                    'id'        => $alert->id,
                    'message'   => $alert->message,
                    'level'     => $alert->level,
                    'time'      => $alert->created_at->format('H:i'),
                    'sensor_id' => $alert->sensor_id,
                ];
            })
            ->toArray();
    }

    /**
     * Fungsi yang dipanggil secara berkala oleh Livewire Polling
     */
    public function updateData(): void
    {
        $this->updateDataState();
        
        // Memicu event re-render grafik Chart.js dengan melemparkan array data langsung
        $this->dispatch('refreshChart', chartData: $this->getChartData());
    }

    public function getChartData(): array
    {
        $data = Sensor::latest()->take(10)->get()->reverse();

        return [
            'labels' => $data->pluck('created_at')->map(fn ($d) => $d->format('H:i'))->toArray(),
            'ph' => $data->pluck('ph')->toArray(),
            'temperature' => $data->pluck('temperature')->toArray(),
            'liquid_temperature' => $data->pluck('liquid_temperature')->toArray(),
            'gas' => $data->pluck('gas')->toArray(),
            'humidity' => $data->pluck('humidity')->toArray(),
        ];
    }

    public function getTableData()
    {
        return Sensor::latest()->take(10)->get();
    }
}