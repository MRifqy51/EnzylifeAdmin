<x-filament-panels::page>

    {{-- = POLLED REALTIME REFRESH = --}}
    <div wire:poll.5s="updateData">

        {{-- ===================== STATS ===================== --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-green-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">pH Level</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $currentStats['ph'] ?? 0 }}</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-yellow-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Air Temperature</p>
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $currentStats['temperature'] ?? 0 }}°C</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-cyan-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Liquid Temp</p>
                <p class="text-2xl font-bold text-cyan-600 dark:text-cyan-400">{{ $currentStats['liquid_temperature'] ?? 0 }}°C</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-red-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Gas (MQ-135)</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $currentStats['gas'] ?? 0 }} ADC</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-blue-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Humidity</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $currentStats['humidity'] ?? 0 }}%</p>
            </div>

        </div>

        {{-- ===================== ALERT NOTIFICATION ===================== --}}
        <div class="mb-6">
            <div class="bg-white rounded-xl shadow p-4 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold dark:text-white">Notifikasi Alert</h2>

                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        {{ count($alerts) }} Riwayat Alert Terbaru
                    </span>

                </div>

                @if(count($alerts) > 0)
                    <div class="space-y-3">
                        @foreach($alerts as $alert)
                            <div class="p-3 rounded-lg border
                                {{ $alert['level'] === 'danger'
                                    ? 'bg-red-50 border-red-300 text-red-800 dark:bg-red-950/20 dark:border-red-800 dark:text-red-400'
                                    : 'bg-yellow-50 border-yellow-300 text-yellow-800 dark:bg-yellow-950/20 dark:border-yellow-800 dark:text-yellow-400' }}">

                                <div class="flex items-center justify-between">
                                    <p class="font-semibold flex items-center gap-1">
                                        {{ $alert['level'] === 'danger' ? '🚨 Bahaya' : '⚠️ Peringatan' }}
                                    </p>
                                    <span class="text-xs opacity-75">{{ $alert['time'] }} WIB</span>
                                </div>

                                <p class="text-sm mt-1">{{ $alert['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-3 rounded-lg bg-green-50 border border-green-300 text-green-700 dark:bg-green-950/20 dark:border-green-800 dark:text-green-400">
                        ✅ Semua parameter fermentasi Eco Enzyme dalam kondisi optimal.
                    </div>
                @endif
            </div>
        </div>

        {{-- ===================== CHART ===================== --}}
        <div class="bg-white p-6 rounded-xl shadow mb-6 dark:bg-gray-800" wire:ignore>
            <h2 class="text-lg font-semibold mb-2 dark:text-white">
                Grafik Real-time Sensor EnzyLife
            </h2>

            <p class="text-sm text-gray-500 mb-4">
                Klik nama sensor pada legenda untuk menampilkan
                atau menyembunyikan garis grafik.
            </p>

            <div class="relative w-full"
                style="height: 500px;">
                <canvas id="sensorChart"></canvas>
            </div>




        {{-- ===================== TABLE ===================== --}}
        @php $rows = $this->getTableData(); @endphp

        <div class="bg-white rounded-xl shadow p-4 dark:bg-gray-800">
            <h2 class="text-lg font-semibold mb-4 dark:text-white">Data Log Sensor Terbaru</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm dark:text-gray-300">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="p-2 text-left">Waktu</th>
                            <th class="p-2 text-left">pH</th>
                            <th class="p-2 text-left">Air Temp</th>
                            <th class="p-2 text-left">Liquid Temp</th>
                            <th class="p-2 text-left">Gas</th>
                            <th class="p-2 text-left">Humidity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="p-2">{{ $row->created_at->format('H:i') }}</td>
                                <td class="p-2 font-medium">{{ $row->ph }}</td>
                                <td class="p-2">{{ $row->temperature }}°C</td>
                                <td class="p-2">{{ $row->liquid_temperature }}°C</td>
                                <td class="p-2">{{ $row->gas }} Raw ADC</td>
                                <td class="p-2">{{ $row->humidity }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada data sensor masuk.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- ===================== REALTIME CHART JS BINDING ===================== --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', initChart);
        document.addEventListener('livewire:navigated', initChart);

        let myChart = null;

            function initChart() {
                const canvas = document.getElementById('sensorChart');
                if (!canvas) return;

                const initialData = @json($this->getChartData());

                // Bersihkan instance grafik dari konfigurasi sebelumnya
                if (window.enzyCharts) {
                    Object.values(window.enzyCharts).forEach(chart => {
                        if (chart) chart.destroy();
                    });
                    window.enzyCharts = null;
                }

                if (myChart) {
                    myChart.destroy();
                }

                myChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: initialData.labels,
                        datasets: [
                            {
                                label: 'pH',
                                data: initialData.ph,
                                borderColor: '#36A2EB',
                                yAxisID: 'yPH',
                                borderWidth: 2,
                                tension: 0.3
                            },
                            {
                                label: 'Suhu Cairan (°C)',
                                data: initialData.liquid_temperature,
                                borderColor: '#00BCD4',
                                yAxisID: 'yTemp',
                                borderWidth: 2,
                                tension: 0.3
                            },
                            {
                                label: 'Suhu Udara (°C) - Belum Valid',
                                data: initialData.temperature,
                                borderColor: '#FF6384',
                                yAxisID: 'yAirTemp',
                                hidden: false,
                                borderWidth: 2,
                                tension: 0.3
                            },
                            {
                                label: 'Kelembapan (%) - Belum Valid',
                                data: initialData.humidity,
                                borderColor: '#4CAF50',
                                yAxisID: 'yHumidity',
                                hidden: false,
                                borderWidth: 2,
                                tension: 0.3
                            },
                            {
                                label: 'Gas MQ-135 (Raw ADC)',
                                data: initialData.gas,
                                borderColor: '#FF9800',
                                yAxisID: 'yGas',
                                hidden: true,
                                borderWidth: 2,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        scales: {
                            yPH: {
                                type: 'linear',
                                position: 'left',
                                min: 0,
                                max: 14,
                                title: {
                                    display: true,
                                    text: 'pH'
                                }
                            },
                            yTemp: {
                                type: 'linear',
                                position: 'right',
                                min: 20,
                                max: 40,
                                title: {
                                    display: true,
                                    text: 'Suhu (°C)'
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            },
                            yAirTemp: {
                                type: 'linear',
                                display: false,
                                position: 'right',
                                min: 0,
                                max: 50,
                                grid: {
                                    drawOnChartArea: false
                                }
                            },
                            yHumidity: {
                                type: 'linear',
                                display: false,
                                min: 0,
                                max: 100
                            },
                            yGas: {
                                type: 'linear',
                                display: false,
                                min: 0,
                                max: 4095
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        }
                    }
                });
            }




        // PERBAIKAN: Menangkap struktur data event Livewire v3 dengan benar
        window.addEventListener('refreshChart', event => {
            const chartData = event.detail?.chartData;

            if (!myChart || !chartData) return;

            // Perbarui label waktu
            myChart.data.labels = chartData.labels;

            // Sesuai urutan dataset pada initChart()
            myChart.data.datasets[0].data = chartData.ph;
            myChart.data.datasets[1].data = chartData.liquid_temperature;
            myChart.data.datasets[2].data = chartData.temperature;
            myChart.data.datasets[3].data = chartData.humidity;
            myChart.data.datasets[4].data = chartData.gas;

            // Perbarui tanpa animasi
            myChart.update('none');
        });

    </script>
    @endpush

</x-filament-panels::page>