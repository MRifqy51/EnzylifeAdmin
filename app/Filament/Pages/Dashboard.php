<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use App\Models\Setting;
use App\Models\Alert;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use App\Models\Device;
use App\Services\ZScoreService;

class Dashboard extends Page
{
    protected static ?string $navigationLabel = 'Dashboard';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;
    protected static ?int $navigationSort = 1;

    public array $alerts = [];
    public array $currentStats = [];
    public array $recentLogs = []; // Tambahan untuk menampung data tabel secara reaktif
    public int $activeAlertCount = 0;

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

        $device = $latest
            ? Device::find($latest->device_id)
            : null;

        $setting = Setting::first();

        $interval = max(
            5,
            (int) ($setting?->collection_interval ?? 10)
        );

        // Batas toleransi data terbaru.
        $staleAfterSeconds = max(60, $interval * 3);

        $isStale = $latest !== null
            && (
                $latest->created_at === null
                || $latest->created_at->isFuture()
                || $latest->created_at->lt(
                    now()->subSeconds($staleAfterSeconds)
                )
            );

        // Memeriksa alert aktif untuk perangkat yang sedang ditampilkan.
        $activeAlerts = Alert::query()
            ->whereNotNull('type')
            ->whereNull('resolved_at')
            ->when(
                $latest,
                fn ($query) => $query->whereHas(
                    'sensor',
                    fn ($sensorQuery) => $sensorQuery
                        ->where('device_id', $latest->device_id)
                )
            );

        $hasDanger = (clone $activeAlerts)
            ->where('level', 'danger')
            ->exists();

        $hasWarning = (clone $activeAlerts)
            ->where('level', 'warning')
            ->exists();

        $status = match (true) {
            $latest === null => 'no_data',
            $isStale => 'stale',
            $hasDanger => 'danger',
            $hasWarning => 'warning',
            default => 'no_active_alert',
        };

        $this->currentStats = [
            'ph' => $latest?->ph,
            'temperature' => $latest?->temperature,
            'liquid_temperature' => $latest?->liquid_temperature,
            'gas' => $latest?->gas,
            'humidity' => $latest?->humidity,
            'status' => $status,
            'last_reading_at' => $latest?->created_at
                ? $latest->created_at->format('d-m-Y H:i:s')
                : null,
        ];


        // 🔥 Hanya tampilkan alert yang belum resolved
        // Menghitung jumlah alert yang masih aktif.
        $this->activeAlertCount = Alert::query()
            ->whereNotNull('type')
            ->whereNull('resolved_at')
            ->count();

        // Mengambil lima alert terbaru,
        // baik yang aktif maupun yang selesai.
        $this->alerts = Alert::query()
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'message' => $alert->message,
                    'level' => $alert->level,
                    'type' => $alert->type,

                    'status' => match (true) {
                        $alert->resolved_at !== null => 'Selesai',
                        $alert->type === null => 'Riwayat Lama',
                        default => 'Aktif',
                    },

                    'time' => $alert->created_at->format('H:i'),

                    'resolved_at' => $alert->resolved_at
                        ? \Illuminate\Support\Carbon::parse(
                            $alert->resolved_at
                        )->format('d-m-Y H:i')
                        : null,

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
