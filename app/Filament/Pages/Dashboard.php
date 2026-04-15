<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dashboard extends Page
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

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