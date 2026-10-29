<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pemantauan Batch Eco Enzyme</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; border-b: 2px solid #2d4a3e; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #2d4a3e; font-size: 18px; }
        .header p { margin: 3px 0 0 0; font-size: 11px; color: #666; }

        .section-title { font-size: 13px; font-weight: bold; color: #2d4a3e; margin-top: 15px; margin-bottom: 8px; border-left: 3px solid #2d4a3e; padding-left: 6px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { padding: 6px; background-color: #f9f9f9; border: 1px solid #eee; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .data-table th, .data-table td { border: 1px solid #ddd; padding: 6px; text-align: center; }
        .data-table th { background-color: #2d4a3e; color: white; font-weight: bold; }

        /* Mencegah header hilang saat tabel lanjut ke Halaman 2 */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="header">
        <h2>ENZYLIFE - LAPORAN PEMANTAUAN BATCH</h2>
        <p>Sistem Monitoring Fermentasi Eco Enzyme Berbasis IoT</p>
    </div>

    <div class="section-title">Informasi Batch Fermentasi</div>
    <table class="info-table">
        <tr>
            <td><strong>Nama Batch:</strong> {{ $batchInfo->nama_batch ?? '—' }}</td>
            <td><strong>Tanggal Mulai:</strong> {{ $batchInfo->tanggal_mulai ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Kapasitas Wadah:</strong> {{ $batchInfo->kapasitas_wadah ?? '—' }} Liter</td>
            <td><strong>Status Batch:</strong> {{ $batchInfo->status ?? 'Active' }}</td>
        </tr>
        <tr>
            <td colspan="2">
                <strong>Target Takaran Bahan:</strong>
                Molase: {{ $batchInfo->target_molase ?? 0 }} KG |
                Sampah Organik: {{ $batchInfo->target_organik ?? 0 }} KG |
                Air: {{ $batchInfo->target_air ?? 0 }} Liter
            </td>
        </tr>
    </table>

    <div class="section-title">Riwayat Pembacaan Sensor</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Waktu (Timestamp)</th>
                <th>pH</th>
                <th>Suhu Udara (°C)</th>
                <th>Suhu Cairan (°C)</th>
                <th>Gas (ppm)</th>
                <th>Kelembapan (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($readings as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y, H:i') }} WIB</td>
                    <td>{{ $row->ph }}</td>
                    <td>{{ $row->temperature }}</td>
                    <td>{{ $row->liquid_temperature }}</td>
                    <td>{{ $row->gas }}</td>
                    <td>{{ $row->humidity }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Tidak ada data sensor pada rentang tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>