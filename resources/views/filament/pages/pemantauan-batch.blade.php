<x-filament-panels::page>
    {{-- ══════════════════════════════════════════════════════
         BATCH CARD
    ══════════════════════════════════════════════════════ --}}
    @php $batch = $this->getBatchData(); @endphp

    {{-- 🔥 BANNER ALERT MILESTONE OTOMATIS --}}
    @if(!empty($batch['milestoneAlert']))
        <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-500 rounded-xl shadow-sm flex items-start gap-3">
            <div class="text-amber-600 dark:text-amber-400 mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h4 class="text-sm font-bold text-amber-800 dark:text-amber-300">Pemberitahuan Milestone Fermentasi (Hari ke-{{ $batch['daysPassed'] }})</h4>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">{{ $batch['milestoneAlert'] }}</p>
            </div>
        </div>
    @endif
 
    <div class="batch-card">
 
        {{-- Top row --}}
        <div class="batch-card__header">
            <div>
                <p class="batch-card__name">{{ $batch['name'] }}</p>
                <p class="batch-card__code">{{ $batch['code'] }}</p>
            </div>
            <span class="batch-card__badge">✦ {{ $batch['status'] }}</span>
        </div>
 
        {{-- Progress bar --}}
        <div class="batch-card__progress-wrap">
            <span class="batch-card__progress-label">Progres Fermentasi</span>
            <span class="batch-card__progress-pct">{{ $batch['progress'] }}%</span>
        </div>
        <div class="batch-card__progress-track">
            <div class="batch-card__progress-fill" style="width: {{ $batch['progress'] }}%"></div>
        </div>
 
        {{-- Radar + Stats --}}
        <div class="batch-card__body">
            {{-- Radar chart --}}
            <div class="batch-card__radar-wrap">
                <canvas id="radarChart" width="180" height="180"></canvas>
            </div>
 
            {{-- Stats --}}
            <div class="batch-card__stats">
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['ph'] }}</span>
                    <span class="batch-card__stat-label">Tingkat pH</span>
                </div>
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['air_temp'] }}°C</span>
                    <span class="batch-card__stat-label">Suhu Udara</span>
                </div>
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['liq_temp'] }}°C</span>
                    <span class="batch-card__stat-label">Suhu Cairan</span>
                </div>
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['gas'] }}</span>
                    <span class="batch-card__stat-label">Gas (ppm)</span>
                </div>
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['humidity'] }}%</span>
                    <span class="batch-card__stat-label">Kelembapan</span>
                </div>
            </div>
        </div>
 
        {{-- Footer --}}
        <div class="batch-card__footer">
            <span class="batch-card__footer-meta">
                🔬 ID Batch · Dimulai {{ $batch['started'] }}
            </span>
            <span class="batch-card__volume">{{ $batch['volume'] }}</span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════
         TRACKING MILESTONE CARD
    ══════════════════════════════════════════════════════ --}}
    <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 my-6">
        <div class="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100 dark:border-gray-800">
            <div class="p-2 bg-green-50 dark:bg-green-950/50 text-green-600 dark:text-green-400 rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012-2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Tracking Milestone Fermentasi</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Status pencapaian siklus saat ini (Hari ke-{{ $batch['daysPassed'] }})</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($batch['milestones'] as $m)
                <div class="p-4 rounded-xl border transition flex items-center justify-between
                    @if($m['status'] === 'completed') bg-green-50/50 border-green-200 dark:bg-green-950/20 dark:border-green-900/40 text-green-800 dark:text-green-300
                    @elseif($m['status'] === 'current') bg-amber-50/80 border-amber-300 dark:bg-amber-950/30 dark:border-amber-700/50 text-amber-900 dark:text-amber-200 shadow-sm
                    @else bg-gray-50/50 border-gray-100 dark:bg-gray-800/30 dark:border-gray-800 text-gray-400 dark:text-gray-500
                    @endif">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider block opacity-75">Hari {{ $m['day'] }} · {{ $m['title'] }}</span>
                        <span class="text-sm font-extrabold block mt-0.5">{{ $m['date'] }}</span>
                    </div>
                    <div>
                        @if($m['status'] === 'completed')
                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase bg-green-200 dark:bg-green-800 text-green-800 dark:text-green-100 rounded-full">Selesai</span>
                        @elseif($m['status'] === 'current')
                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase bg-amber-500 text-white rounded-full animate-pulse">Hari Ini!</span>
                        @else
                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">Akan Datang</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
 
    {{-- ══════════════════════════════════════════════════════
         FILTER BY DATE RANGE
    ══════════════════════════════════════════════════════ --}}
    <div class="filter-card">
        <div class="filter-card__title">
            <svg xmlns="http://www.w3.org/2000/svg" class="filter-card__icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
            </svg>
            Filter Berdasarkan Rentang Tanggal
        </div>
 
        <div class="filter-card__row">
            <div class="filter-card__field">
                <label class="filter-card__label">Tanggal Mulai</label>
                <input
                    type="date"
                    wire:model="startDate"
                    class="filter-card__input"
                />
            </div>
            <div class="filter-card__field">
                <label class="filter-card__label">Tanggal Selesai</label>
                <input
                    type="date"
                    wire:model="endDate"
                    class="filter-card__input"
                />
            </div>
            <div class="filter-card__actions">
                <button wire:click="applyFilter" class="btn-apply">
                    Terapkan Filter
                </button>
                <button wire:click="exportCsv" class="btn-export">
                    Ekspor CSV
                </button>
                {{-- Tambahan Tombol PDF --}}
                <button wire:click="exportPdf" class="btn-export" style="background-color: #b91c1c; color: white;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;display:inline;margin-right:4px;">
                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                    </svg>
                    Ekspor PDF
                </button>
            </div>
        </div>
    </div>
 
    {{-- ══════════════════════════════════════════════════════
         SENSOR READINGS TABLE
    ══════════════════════════════════════════════════════ --}}
    @php
        $readings   = $this->getSensorReadings();
        $totalCount = $this->getTotalCount();
    @endphp
 
    <div class="table-card">
        <div class="table-card__header">
            <span class="table-card__title">Riwayat Pembacaan Sensor</span>
        </div>
 
        <div class="table-card__wrap">
            <table class="sensor-table">
                <thead>
                    <tr>
                        <th>Waktu (Timestamp)</th>
                        <th>Tingkat pH</th>
                        <th>Suhu Udara (°C)</th>
                        <th>Suhu Cairan (°C)</th>
                        <th>Konsentrasi Gas (ppm)</th>
                        <th>Kelembapan (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($readings as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y, H:i') }} WIB</td>
                            <td class="font-medium">{{ $row->ph }}</td>
                            <td>{{ $row->temperature }}°C</td>
                            <td>{{ $row->liquid_temperature }}°C</td>
                            <td>{{ $row->gas }} ppm</td>
                            <td>{{ $row->humidity }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-empty">Data sensor tidak ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
 
        <div class="table-card__footer">
            Menampilkan {{ $readings->count() }} dari total {{ $totalCount }} data. Ekspor ke CSV untuk mengunduh seluruh data.
        </div>
    </div>
 
    {{-- ══════════════════════════════════════════════════════
         CHART.JS RADAR
    ══════════════════════════════════════════════════════ --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('livewire:navigated', initRadar);
        document.addEventListener('DOMContentLoaded',   initRadar);
 
        function initRadar() {
            const canvas = document.getElementById('radarChart');
            if (!canvas) return;
            if (canvas._chartInstance) { canvas._chartInstance.destroy(); }
 
            const radar = @json($batch['radar']);
            canvas._chartInstance = new Chart(canvas, {
                type: 'radar',
                data: {
                    labels: Object.keys(radar),
                    datasets: [{
                        data:            Object.values(radar),
                        backgroundColor: 'rgba(45,74,62,0.25)',
                        borderColor:     '#2d4a3e',
                        borderWidth:     2,
                        pointBackgroundColor: '#2d4a3e',
                        pointRadius:     3,
                    }]
                },
                options: {
                    responsive: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        r: {
                            min: 0, max: 100,
                            ticks: { display: false, stepSize: 25 },
                            grid:        { color: 'rgba(45,74,62,0.15)' },
                            angleLines:  { color: 'rgba(45,74,62,0.15)' },
                            pointLabels: {
                                color:    '#4a6b5c',
                                font:     { size: 10 },
                            },
                        }
                    }
                }
            });
        }
    </script>
    @endpush
</x-filament-panels::page>