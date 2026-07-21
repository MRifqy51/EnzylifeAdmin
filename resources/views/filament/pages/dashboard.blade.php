<x-filament-panels::page>

    {{-- = POLLED REALTIME REFRESH = --}}
    <div wire:poll.5s="updateData">

        {{-- ===================== STATS ===================== --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-green-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Tingkat pH</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $currentStats['ph'] ?? 0 }}</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-yellow-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Suhu Udara</p>
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $currentStats['temperature'] ?? 0 }}°C</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-cyan-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Suhu Cairan</p>
                <p class="text-2xl font-bold text-cyan-600 dark:text-cyan-400">{{ $currentStats['liquid_temperature'] ?? 0 }}°C</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-red-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Konsentrasi Gas</p>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $currentStats['gas'] ?? 0 }} ppm</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800 border-l-4 border-blue-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Kelembapan</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $currentStats['humidity'] ?? 0 }}%</p>
            </div>

        </div>

        {{-- ===================== ALERT NOTIFICATION ===================== --}}
        <div class="mb-6">
            <div class="bg-white rounded-xl shadow p-4 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold dark:text-white">Notifikasi Alert</h2>

                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        {{ count($alerts) > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                        {{ count($alerts) > 0 ? count($alerts) . ' Alert Terdeteksi' : 'Sistem Normal' }}
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
            <h2 class="text-lg font-semibold mb-4 dark:text-white">Grafik Real-time Sensor</h2>
            <canvas id="sensorChart" height="100"></canvas>
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
                            <th class="p-2 text-left">Suhu Udara</th>
                            <th class="p-2 text-left">Suhu Cairan</th>
                            <th class="p-2 text-left">Gas</th>
                            <th class="p-2 text-left">Kelembapan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="p-2">{{ $row->created_at->format('H:i') }}</td>
                                <td class="p-2 font-medium">{{ $row->ph }}</td>
                                <td class="p-2">{{ $row->temperature }}°C</td>
                                <td class="p-2">{{ $row->liquid_temperature }}°C</td>
                                <td class="p-2">{{ $row->gas }} ppm</td>
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
            const ctx = document.getElementById('sensorChart');
            if (!ctx) return;

            const initialData = @json($this->getChartData());

            if (myChart) {
                myChart.destroy();
            }

            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: initialData.labels,
                    datasets: [
                        {
                            label: 'pH',
                            data: initialData.ph,
                            borderColor: '#36A2EB',
                            backgroundColor: 'rgba(54,162,235,0.1)',
                            tension: 0.3,
                            borderWidth: 2
                        },
                        {
                            label: 'Suhu Udara',
                            data: initialData.temperature,
                            borderColor: '#FF6384',
                            backgroundColor: 'rgba(255,99,132,0.1)',
                            tension: 0.3,
                            borderWidth: 2
                        },
                        {
                            label: 'Suhu Cairan',
                            data: initialData.liquid_temperature,
                            borderColor: '#00BCD4',
                            backgroundColor: 'rgba(0,188,212,0.1)',
                            tension: 0.3,
                            borderWidth: 2
                        },
                        {
                            label: 'Gas',
                            data: initialData.gas,
                            borderColor: '#FF9800',
                            backgroundColor: 'rgba(255,152,0,0.1)',
                            tension: 0.3,
                            borderWidth: 2
                        },
                        {
                            label: 'Kelembapan',
                            data: initialData.humidity,
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76,175,80,0.1)',
                            tension: 0.3,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: false }
                    },
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        }

        window.addEventListener('refreshChart', event => {
            const chartData = event.detail.chartData; 
            if (myChart && chartData) {
                myChart.data.labels = chartData.labels;
                myChart.data.datasets[0].data = chartData.ph;
                myChart.data.datasets[1].data = chartData.temperature;
                myChart.data.datasets[2].data = chartData.liquid_temperature;
                myChart.data.datasets[3].data = chartData.gas;
                myChart.data.datasets[4].data = chartData.humidity;
                myChart.update('none'); 
            }
        });
    </script>
    @endpush

</x-filament-panels::page>