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

        {{-- STATUS MONITORING --}}
        @php
            $monitorStatus = $currentStats['status'] ?? 'no_data';

            $statusInfo = match ($monitorStatus) {
                'no_data' => [
                    'label' => 'Belum Ada Data',
                    'color' => 'bg-gray-100 text-gray-800',
                    'message' => 'Belum ada pembacaan sensor yang tersimpan.',
                ],
                'stale' => [
                    'label' => 'Data Tidak Terbaru',
                    'color' => 'bg-orange-100 text-orange-800',
                    'message' => 'Periksa koneksi ESP32, MQTT, dan proses penerimaan data.',
                ],
                'unverified' => [
                    'label' => 'Belum Terverifikasi',
                    'color' => 'bg-gray-100 text-gray-800',
                    'message' => 'Baseline pengukuran cairan belum dimulai.',
                ],
                'collecting' => [
                    'label' => 'Mengumpulkan Baseline',
                    'color' => 'bg-blue-100 text-blue-800',
                    'message' => 'Menunggu 30 referensi valid untuk pH dan suhu cairan.',
                ],
                'danger' => [
                    'label' => 'Bahaya',
                    'color' => 'bg-red-100 text-red-800',
                    'message' => 'Terdapat alert bahaya yang masih aktif.',
                ],
                'warning' => [
                    'label' => 'Peringatan',
                    'color' => 'bg-yellow-100 text-yellow-800',
                    'message' => 'Terdapat alert peringatan yang masih aktif.',
                ],
                default => [
                    'label' => 'Tidak Ada Peringatan Aktif',
                    'color' => 'bg-green-100 text-green-800',
                    'message' => 'Tidak ada alert aktif pada perangkat yang dipantau. '
                        . 'Hal ini bukan bukti bahwa fermentasi sudah optimal.',
                ],
            };
        @endphp

        <div class="mb-6 rounded-xl bg-white p-4 shadow dark:bg-gray-800">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold dark:text-white">
                        Status Monitoring EnzyLife
                    </h2>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $statusInfo['message'] }}
                    </p>
                </div>

                <span class="rounded-full px-3 py-2 text-sm font-semibold
                            {{ $statusInfo['color'] }}">
                    {{ $statusInfo['label'] }}
                </span>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Pembacaan terakhir:
                {{ $currentStats['last_reading_at'] ?? 'Belum tersedia' }}
            </p>
        </div>


        {{-- ===================== ALERT NOTIFICATION ===================== --}}
        <div class="mb-6">
            <div class="bg-white rounded-xl shadow p-4 dark:bg-gray-800">

                {{-- Judul dan ringkasan alert --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold dark:text-white">
                        Notifikasi Alert
                    </h2>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            {{ $activeAlertCount > 0
                                ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' }}">
                            {{ $activeAlertCount }} Alert Aktif
                        </span>

                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            bg-blue-100 text-blue-700
                            dark:bg-blue-900/30 dark:text-blue-400">
                            {{ count($alerts) }} Riwayat Terbaru
                        </span>
                    </div>
                </div>

                {{-- Daftar lima alert terbaru --}}
                @if(count($alerts) > 0)
                    <div class="space-y-3">

                        @foreach($alerts as $alert)
                            @php
                                $status = $alert['status'] ?? 'Riwayat Lama';
                                $isActive = $status === 'Aktif';
                                $isDanger = $alert['level'] === 'danger';
                            @endphp

                            <div class="p-4 rounded-lg border
                                {{ !$isActive
                                    ? 'bg-gray-50 border-gray-200 text-gray-700 dark:bg-gray-700/30 dark:border-gray-600 dark:text-gray-300'
                                    : ($isDanger
                                        ? 'bg-red-50 border-red-300 text-red-800 dark:bg-red-950/20 dark:border-red-800 dark:text-red-400'
                                        : 'bg-yellow-50 border-yellow-300 text-yellow-800 dark:bg-yellow-950/20 dark:border-yellow-800 dark:text-yellow-400') }}">

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold">
                                            {{ $isDanger ? '🚨 Bahaya' : '⚠️ Peringatan' }}
                                        </span>

                                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                                            {{ match ($status) {
                                                'Aktif' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                                'Selesai' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                                default => 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
                                            } }}">
                                            {{ $status }}
                                        </span>
                                    </div>

                                    <span class="text-xs opacity-75">
                                        {{ $alert['time'] }}
                                    </span>
                                </div>

                                <p class="text-sm mt-2">
                                    @switch($alert['type'])

                                        @case('zscore_ph')
                                            Terdeteksi perubahan nilai pH yang tidak biasa.
                                            Silakan periksa kondisi sensor dan cairan fermentasi.
                                            @break

                                        @case('zscore_liquid_temperature')
                                            Terdeteksi perubahan suhu cairan yang tidak biasa.
                                            Silakan periksa kondisi sensor dan cairan fermentasi.
                                            @break

                                        @case('threshold_ph')
                                            Nilai pH berada di luar batas yang telah ditentukan.
                                            Silakan periksa kondisi cairan fermentasi.
                                            @break

                                        @case('threshold_liquid_temperature')
                                            Suhu cairan berada di luar batas yang telah ditentukan.
                                            Silakan periksa kondisi fermentasi.
                                            @break

                                        @default
                                            {{ $alert['message'] }}

                                    @endswitch
                                </p>


                                @if(!empty($alert['resolved_at']))
                                    <p class="text-xs mt-2 opacity-75">
                                        Selesai pada:
                                        {{ $alert['resolved_at'] }}
                                    </p>
                                @endif

                            </div>
                        @endforeach

                    </div>
                @else
                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-200
                        text-gray-600 dark:bg-gray-700/30 dark:border-gray-600
                        dark:text-gray-300">
                        Belum ada riwayat peringatan yang tercatat.
                    </div>
                @endif

                {{-- Status saat tidak ada peringatan aktif --}}
                @if($activeAlertCount === 0)
                    <p class="text-sm text-green-600 dark:text-green-400 mt-4">
                        ✅ Tidak ada alert aktif yang tercatat saat ini.
                    </p>
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
            </div> {{-- Penutup container grafik wire:ignore --}}




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
                                <td colspan="6" class="p-4 text-center text-gray-500 dark:text-gray-400">
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
