<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Batch;
use Filament\Notifications\Notification;

class KalkulatorSop extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Kalkulator & SOP';
    protected static ?string $title = 'Kalkulator Formulasi & SOP Batching';
    protected static ?int $navigationSort = 2; 

    protected string $view = 'filament.pages.kalkulator-sop';

    public $nama_batch = '';
    public $id_devices = 1;
    public $tanggal_mulai;
    public $kapasitas_wadah = null;

    public $molase = 0;
    public $sampah_organik = 0;
    public $air = 0;
    public $vol_bahan = 0;
    public $space_aman = 0;

    public $sop_steps = [
        1 => false,
        2 => false,
        3 => false,
        4 => false,
    ];

    public function mount(): void
    {
        $this->tanggal_mulai = date('Y-m-d');
    }

    public function updatedKapasitasWadah()
    {
        if (is_numeric($this->kapasitas_wadah) && $this->kapasitas_wadah > 0) {
            $this->hitungFormulasi();
        } else {
            $this->resetKalkulasi();
        }
    }

    public function hitungFormulasi()
    {
        $kapasitas = floatval($this->kapasitas_wadah);
        
        // Logika takaran standar rasio 1 : 3 : 10
        $this->air = round($kapasitas * 0.625, 2);
        $this->molase = round($kapasitas * 0.0625, 2);
        $this->sampah_organik = round($kapasitas * 0.1875, 2);
        
        $this->vol_bahan = $this->molase + $this->sampah_organik + $this->air;
        $this->space_aman = round($kapasitas - $this->vol_bahan, 2);
    }

    public function resetKalkulasi()
    {
        $this->molase = 0;
        $this->sampah_organik = 0;
        $this->air = 0;
        $this->vol_bahan = 0;
        $this->space_aman = 0;
    }

    public function simpanBatch()
    {
        // 1. Validasi Form Input Utama
        $this->validate([
            'nama_batch'      => 'required|string|min:3|max:100',
            'id_devices'      => 'required|integer',
            'tanggal_mulai'   => 'required|date',
            'kapasitas_wadah' => 'required|numeric|min:1',
        ], [
            'nama_batch.required'      => 'Nama batch wajib diisi!',
            'nama_batch.min'           => 'Nama batch minimal 3 karakter.',
            'kapasitas_wadah.required' => 'Kapasitas wadah wajib diisi!',
            'kapasitas_wadah.min'      => 'Kapasitas wadah harus lebih besar dari 0 Liter!',
            'kapasitas_wadah.numeric'  => 'Kapasitas wadah harus berupa angka!',
        ]);

        // 2. Validasi SOP Interaktif (Wajib dicentang semua sebelum disimpan)
        if (in_array(false, $this->sop_steps, true)) {
            Notification::make()
                ->title('SOP Belum Lengkap!')
                ->body('Harap selesaikan dan centang seluruh tahapan SOP Batching sebelum menyimpan batch.')
                ->danger()
                ->send();
            return;
        }

        $this->hitungFormulasi();

        // 3. Nonaktifkan batch lama jika ada (agar hanya ada 1 batch aktif)
        Batch::where('id_devices', $this->id_devices)->update(['status' => 'Completed']);

        // 4. Simpan batch baru ke database
        Batch::create([
            'id_devices'      => $this->id_devices,
            'nama_batch'      => $this->nama_batch,
            'tanggal_mulai'   => $this->tanggal_mulai,
            'kapasitas_wadah' => $this->kapasitas_wadah,
            'target_organik'  => $this->sampah_organik,
            'target_molase'   => $this->molase,
            'target_air'      => $this->air,
            'status'          => 'Active',
        ]);

        Notification::make()
            ->title('Berhasil!')
            ->body('SOP tuntas diverifikasi & batch baru sukses disimpan ke database.')
            ->success()
            ->send();

        // 5. Reset Form dan Checklist setelah berhasil submit
        $this->reset(['nama_batch', 'kapasitas_wadah']);
        $this->resetKalkulasi();
        $this->sop_steps = [1 => false, 2 => false, 3 => false, 4 => false];
    }
}