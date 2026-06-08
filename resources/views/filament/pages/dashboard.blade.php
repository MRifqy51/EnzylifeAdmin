<x-filament-panels::page>

    {{-- 
        = POLLED REALTIME REFRESH =
        Menambahkan polling otomatis setiap 5 detik ke fungsi updateData() di backend.
        Semua variabel $currentStats, $alerts, dan $rows di bawah akan diperbarui otomatis secara background.
    --}}
    <div wire:poll.5s="updateData">

        {{-- ===================== STATS ===================== --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">pH Level</p>
                <p class="text-2xl font-bold text-green-600">{{ $currentStats['ph'] ?? 0 }}</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">Temperature</p>
                <p class="text-2xl font-bold text-yellow-600">{{ $currentStats['temperature'] ?? 0 }}°C</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">Gas</p>
                <p class="text-2xl font-bold text-red-600">{{ $currentStats['gas'] ?? 0 }} ppm</p>
            </div>

            <div class="p-4 bg-white rounded-xl shadow dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">Humidity</p>
                <p class="text-2xl font-bold text-blue-600">{{ $currentStats['humidity'] ?? 0 }}%</p>
            </div>

        </div>

        {{-- ===================== ALERT NOTIFICATION ===================== --}}
        <div class="mb-6">
            <div class="bg-white rounded-xl shadow p-4 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold dark:text-white">Notifikasi Alert</h2>

                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        {{ count($alerts) > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                        {{ count($alerts) > 0 ? count($alerts) . ' Alert' : 'Normal' }}
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
                                    <p class="font-semibold">
                                        {{ $alert['level'] === 'danger' ? '🚨 Bahaya' : '⚠️ Peringatan' }}
                                    </p>
                                    <span class="text-xs">{{ $alert['time'] }}</span>
                                </div>

                                <p class="text-sm mt-1">{{ $alert['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-3 rounded-lg bg-green-50 border border-green-300 text-green-700 dark:bg-green-950/20 dark:border-green-800 dark:text-green-400">
                        ✅ Semua parameter fermentasi dalam kondisi normal.
                    </div>
                @endif
            </div>
        </div>

        {{-- ===================== CHART ===================== --}}
        <div class="bg-white p-6 rounded-xl shadow mb-6 dark:bg-gray-800" wire:ignore>
            <h2 class="text-lg font-semibold mb-4 dark:text-white">Sensor Data</h2>
            <canvas id="sensorChart" height="100"></canvas>
        </div>

        {{-- ===================== TABLE ===================== --}}
        @php $rows = $this->getTableData(); @endphp

        <div class="bg-white rounded-xl shadow p-4 dark:bg-gray-800">
            <h2 class="text-lg font-semibold mb-4 dark:text-white">Recent Sensor Data</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm dark:text-gray-300">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="p-2 text-left">Time</th>
                            <th class="p-2 text-left">pH</th>
                            <th class="p-2 text-left">Temperature</th>
                            <th class="p-2 text-left">Gas</th>
                            <th class="p-2 text-left">Humidity</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="p-2">{{ $row->created_at->format('H:i') }}</td>
                                <td class="p-2">{{ $row->ph }}</td>
                                <td class="p-2">{{ $row->temperature }}</td>
                                <td class="p-2">{{ $row->gas }}</td>
                                <td class="p-2">{{ $row->humidity }}</td>
                                <td class="p-2">
                                    <span class="px-2 py-1 rounded text-white text-xs
                                        @if($row->status === 'optimal') bg-green-500
                                        @elseif($row->status === 'warning') bg-yellow-500
                                        @else bg-red-500
                                        @endif
                                    ">
                                        {{ $row->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    No data available
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

            // Ambil data inisial dari backend lewat blade PHP
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
                            backgroundColor: 'rgba(54,162,235,0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Temperature',
                            data: initialData.temperature,
                            borderColor: '#FF6384',
                            backgroundColor: 'rgba(255,99,132,0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Gas',
                            data: initialData.gas,
                            borderColor: '#FF9800',
                            backgroundColor: 'rgba(255,152,0,0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Humidity',
                            data: initialData.humidity,
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76,175,80,0.2)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        }

        // MENANGKAP TRIGGER DARI BACKEND UNTUK UPDATE DATA GRAFIK SECARA REAL-TIME
        window.addEventListener('refreshChart', event => {
            const chartData = event.detail.data;
            if (myChart) {
                myChart.data.labels = chartData.labels;
                myChart.data.datasets[0].data = chartData.ph;
                myChart.data.datasets[1].data = chartData.temperature;
                myChart.data.datasets[2].data = chartData.gas;
                myChart.data.datasets[3].data = chartData.humidity;
                myChart.update('none'); // Menggunakan mode 'none' agar transisi pergeseran grafik halus tanpa berkedip
            }
        });
    </script>
    @endpush

</x-filament-panels::page>