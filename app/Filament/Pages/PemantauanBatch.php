<?php

namespace App\Filament\Pages;

use App\Models\Sensor;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function getBatchData(): array
    {
        $latestSensor = Sensor::latest('created_at')->first();
        $activeBatch = \App\Models\Batch::where('status', 'Active')->latest('created_at')->first();

        if ($activeBatch) {
            $startDate = Carbon::parse($activeBatch->tanggal_mulai);
            $batchName = $activeBatch->nama_batch;
            $volume = $activeBatch->kapasitas_wadah . 'L';
        } else {
            $startDate = Carbon::parse('2026-07-11');
            $batchName = 'Fermentasi Eco Enzyme';
            $volume = '5L';
        }

        $totalDaysNeeded = 90;
        $daysPassed = max(0, $startDate->diffInDays(now()));
        $progress = round(min(100, ($daysPassed / $totalDaysNeeded) * 100));

        // 🔥 Logika Deteksi Milestone untuk Banner Alert
        $milestoneAlert = null;
        if ($daysPassed == 7) {
            $milestoneAlert = "PENTING: Hari ini adalah Milestone Hari ke-7 (Jadwal Panen Gas / Pelepasan Tekanan Wadah)! Pastikan pengecekan tekanan wadah dilakukan.";
        } elseif ($daysPassed == 30) {
            $milestoneAlert = "PENTING: Hari ini adalah Milestone Hari ke-30 (Jadwal Cek Kondisi Sampah Organik & Lapisan Permukaan).";
        } elseif ($daysPassed >= 90) {
            $milestoneAlert = "SELAMAT: Batch telah mencapai Hari ke-90+. Fermentasi siap dipanen (Panen Utama Eco Enzyme)!";
        }

        // 🔥 Data untuk Tracking Milestone Visual
        $milestones = [
            [
                'day' => 7,
                'title' => 'Panen Gas',
                'date' => $startDate->copy()->addDays(7)->format('d M Y'),
                'status' => $daysPassed > 7 ? 'completed' : ($daysPassed == 7 ? 'current' : 'upcoming')
            ],
            [
                'day' => 30,
                'title' => 'Cek Organik',
                'date' => $startDate->copy()->addDays(30)->format('d M Y'),
                'status' => $daysPassed > 30 ? 'completed' : ($daysPassed == 30 ? 'current' : 'upcoming')
            ],
            [
                'day' => 90,
                'title' => 'Panen Utama',
                'date' => $startDate->copy()->addDays(90)->format('d M Y'),
                'status' => $daysPassed > 90 ? 'completed' : ($daysPassed == 90 ? 'current' : 'upcoming')
            ],
        ];

        return [
            'name'           => $batchName,
            'code'           => 'ECO-V-' . sprintf('%02d', $activeBatch?->id_batch ?? 1),
            'status'         => 'AKTIF',
            'progress'       => $progress,
            'daysPassed'     => $daysPassed,      // <--- Kunci yang dicari view
            'milestoneAlert' => $milestoneAlert,  // <--- Kunci untuk alert
            'milestones'     => $milestones,      // <--- Kunci untuk kotak tracking milestone

            'ph'       => $latestSensor?->ph ?? '—',
            'air_temp' => $latestSensor?->temperature ?? '—',
            'liq_temp' => $latestSensor?->liquid_temperature ?? '—',
            'gas'      => $latestSensor?->gas ?? '—',
            'humidity' => $latestSensor?->humidity ?? '—',

            'started'  => $startDate->format('Y-m-d'),
            'volume'   => $volume,

            'radar' => [
                'pH'       => round(min(100, (($latestSensor?->ph ?? 7) / 14) * 100)),
                'Air Temp' => round(min(100, (($latestSensor?->temperature ?? 25) / 50) * 100)),
                'Liq Temp' => round(min(100, (($latestSensor?->liquid_temperature ?? 25) / 50) * 100)),
                'Gas'      => round(min(100, (($latestSensor?->gas ?? 400) / 2000) * 100)),
                'Humidity' => round($latestSensor?->humidity ?? 50),
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

    // 🔥 Export PDF (Dipisah menjadi method tersendiri di luar exportCsv)
    public function exportPdf()
    {
        // 1. Ambil data batch aktif/terbaru dari database batches
        $batchInfo = \App\Models\Batch::latest('tanggal_mulai')->first();

        // 2. Ambil data sensor berdasarkan filter tanggal (sama seperti CSV)
        $readings = $this->getSensorReadings();

        // 3. Load view khusus untuk cetak PDF (Tambahkan tanda $ pada $pdf)
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.laporan-batch', [
            'batchInfo' => $batchInfo,
            'readings'  => $readings,
            'startDate' => $this->startDate,
            'endDate'   => $this->endDate,
        ]);

        // 4. Download file PDF ke browser
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'laporan-batch-eco-enzyme-' . now()->format('Y-m-d') . '.pdf');
    }

}