<x-filament-panels::page>


    {{-- ===================== STATS ===================== --}}
    @php $stats = $this->getStats(); @endphp

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

        <div class="p-4 bg-white rounded-xl shadow">
            <p class="text-sm text-gray-500">pH Level</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['ph'] }}</p>
        </div>

        <div class="p-4 bg-white rounded-xl shadow">
            <p class="text-sm text-gray-500">Temperature</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['temperature'] }}°C</p>
        </div>

        <div class="p-4 bg-white rounded-xl shadow">
            <p class="text-sm text-gray-500">Gas</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['gas'] }} ppm</p>
        </div>

        <div class="p-4 bg-white rounded-xl shadow">
            <p class="text-sm text-gray-500">Humidity</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['humidity'] }}%</p>
        </div>

    </div>

    {{-- ===================== ALERT NOTIFICATION ===================== --}}
@php $alerts = $this->getAlerts(); @endphp

<div class="mb-6">
    <div class="bg-white rounded-xl shadow p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold">Notifikasi Alert</h2>

            <span class="px-3 py-1 rounded-full text-xs font-semibold
                {{ count($alerts) > 0 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                {{ count($alerts) > 0 ? count($alerts) . ' Alert' : 'Normal' }}
            </span>
        </div>

        @if(count($alerts) > 0)
            <div class="space-y-3">
                @foreach($alerts as $alert)
                    <div class="p-3 rounded-lg border
                        {{ $alert['level'] === 'danger'
                            ? 'bg-red-50 border-red-300 text-red-800'
                            : 'bg-yellow-50 border-yellow-300 text-yellow-800' }}">
                        
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
            <div class="p-3 rounded-lg bg-green-50 border border-green-300 text-green-700">
                ✅ Semua parameter fermentasi dalam kondisi normal.
            </div>
        @endif
    </div>
</div>

    {{-- ===================== CHART ===================== --}}
    @php $chart = $this->getChartData(); @endphp

    <div class="bg-white p-6 rounded-xl shadow mb-6">
        <h2 class="text-lg font-semibold mb-4">Sensor Data</h2>
        <canvas id="sensorChart" height="100"></canvas>
    </div>

    {{-- ===================== TABLE ===================== --}}
    @php $rows = $this->getTableData(); @endphp

    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="text-lg font-semibold mb-4">Recent Sensor Data</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
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
                        <tr class="border-b">
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
                            <td colspan="6" class="p-4 text-center text-gray-500">
                                No data available
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===================== CHART JS ===================== --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', initChart);
        document.addEventListener('livewire:navigated', initChart);

        function initChart() {
            const ctx = document.getElementById('sensorChart');
            if (!ctx) return;

            if (ctx.chart) {
                ctx.chart.destroy();
            }

            ctx.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [
                        {
                            label: 'pH',
                            data: @json($chart['ph']),
                            borderColor: '#36A2EB',
                            backgroundColor: 'rgba(54,162,235,0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Temperature',
                            data: @json($chart['temperature']),
                            borderColor: '#FF6384',
                            backgroundColor: 'rgba(255,99,132,0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Gas',
                            data: @json($chart['gas']),
                            borderColor: '#FF9800',
                            backgroundColor: 'rgba(255,152,0,0.2)',
                            tension: 0.4
                        },
                        {
                            label: 'Humidity',
                            data: @json($chart['humidity']),
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76,175,80,0.2)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
    </script>
    @endpush

</x-filament-panels::page>