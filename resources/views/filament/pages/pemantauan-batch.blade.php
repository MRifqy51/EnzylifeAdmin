<x-filament-panels::page>
    {{-- ══════════════════════════════════════════════════════
         BATCH CARD
    ══════════════════════════════════════════════════════ --}}
    @php $batch = $this->getBatchData(); @endphp
 
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
            <span class="batch-card__progress-label">Fermentation Progress</span>
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
                    <span class="batch-card__stat-label">pH</span>
                </div>
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['temp'] }}°</span>
                    <span class="batch-card__stat-label">Temp</span>
                </div>
                <div class="batch-card__stat">
                    <span class="batch-card__stat-value">{{ $batch['gas'] }}</span>
                    <span class="batch-card__stat-label">ppm</span>
                </div>
            </div>
        </div>
 
        {{-- Footer --}}
        <div class="batch-card__footer">
            <span class="batch-card__footer-meta">
                🔬 Gas ID · Started {{ $batch['started'] }}
            </span>
            <span class="batch-card__volume">{{ $batch['volume'] }}</span>
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
            Filter by Date Range
        </div>
 
        <div class="filter-card__row">
            <div class="filter-card__field">
                <label class="filter-card__label">Start Date</label>
                <input
                    type="date"
                    wire:model="startDate"
                    class="filter-card__input"
                />
            </div>
            <div class="filter-card__field">
                <label class="filter-card__label">End Date</label>
                <input
                    type="date"
                    wire:model="endDate"
                    class="filter-card__input"
                />
            </div>
            <div class="filter-card__actions">
                <button wire:click="applyFilter" class="btn-apply">
                    Apply Filter
                </button>
                <button wire:click="exportCsv" class="btn-export">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;display:inline;margin-right:4px;">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Export CSV
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
            <span class="table-card__title">Sensor Readings</span>
        </div>
 
        <div class="table-card__wrap">
            <table class="sensor-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>pH Level</th>
                        <th>Liquid Temp (°C)</th>
                        <th>Gas Conc. (ppm)</th>
                        <th>Humidity (%)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($readings as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('M j, Y, h:i A') }}</td>
                            <td>{{ $row->ph }}</td>
                            <td>{{ $row->temperature }}</td>
                            <td>{{ $row->gas }}</td>
                            <td>{{ $row->humidity }}</td>
                            <td>{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-empty">No sensor data found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
 
        <div class="table-card__footer">
            Showing first {{ $readings->count() }} of {{ $totalCount }} records. Export to CSV for full data.
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
