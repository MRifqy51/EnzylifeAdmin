<x-filament-panels::page>
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">

        <!-- KOLOM KIRI: FORM INPUT & HASIL KALKULASI -->
        <div class="xl:col-span-7 space-y-6">

            <!-- Form Input Batch Baru -->
            <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                    <div class="p-2.5 bg-green-50 dark:bg-green-950/50 text-green-600 dark:text-green-400 rounded-xl">
                        <x-filament::icon icon="heroicon-o-beaker" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Form Input Batch Baru</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Atur parameter dan kapasitas wadah fermentasi</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">Nama Batch</label>
                        <input type="text" wire:model="nama_batch" placeholder="Masukkan nama batch (misal: Batch EcoEnzyme #01)" class="w-full px-3.5 py-2.5 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 text-gray-900 dark:text-white border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-green-500 text-sm">
                        @error('nama_batch') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">Perangkat IoT Target</label>
                            <input type="text" wire:model="id_devices" readonly class="w-full px-3.5 py-2.5 border rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 border-gray-200 dark:border-gray-700 text-sm cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">Tanggal Mulai</label>
                            <input type="date" wire:model="tanggal_mulai" class="w-full px-3.5 py-2.5 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 text-gray-900 dark:text-white border-gray-200 dark:border-gray-700 text-sm">
                            @error('tanggal_mulai') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">Kapasitas Wadah (Liter)</label>
                        <input type="number" min="1" wire:model.live="kapasitas_wadah" placeholder="Masukkan angka kapasitas (misal: 16)" class="w-full px-3.5 py-2.5 border rounded-xl bg-gray-50/50 dark:bg-gray-800/50 text-gray-900 dark:text-white border-gray-200 dark:border-gray-700 focus:ring-2 focus:ring-green-500 text-sm font-bold">
                        @error('kapasitas_wadah') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <!-- Hasil Kalkulasi Takaran Bahan -->
            <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                    <div class="p-2.5 bg-green-50 dark:bg-green-950/50 text-green-600 dark:text-green-400 rounded-xl">
                        <x-filament::icon icon="heroicon-o-calculator" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Hasil Kalkulasi Takaran Bahan</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Rekomendasi rasio formula optimal (1 : 3 : 10)</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-5 text-center">
                    <div class="p-4 bg-gradient-to-br from-green-700 to-green-800 text-white rounded-xl shadow-md">
                        <span class="block text-[10px] uppercase tracking-wider font-medium text-green-200 mb-1">Molase</span>
                        <span class="text-lg md:text-xl font-extrabold">{{ $molase }} <span class="text-xs font-normal">KG</span></span>
                    </div>
                    <div class="p-4 bg-gradient-to-br from-green-700 to-green-800 text-white rounded-xl shadow-md">
                        <span class="block text-[10px] uppercase tracking-wider font-medium text-green-200 mb-1">Sampah Organik</span>
                        <span class="text-lg md:text-xl font-extrabold">{{ $sampah_organik }} <span class="text-xs font-normal">KG</span></span>
                    </div>
                    <div class="p-4 bg-gradient-to-br from-green-700 to-green-800 text-white rounded-xl shadow-md">
                        <span class="block text-[10px] uppercase tracking-wider font-medium text-green-200 mb-1">Air</span>
                        <span class="text-lg md:text-xl font-extrabold">{{ $air }} <span class="text-xs font-normal">L</span></span>
                    </div>
                </div>

                <div class="p-3.5 bg-gray-50 dark:bg-gray-800/50 rounded-xl flex justify-between items-center text-xs font-medium text-gray-600 dark:text-gray-300 mb-5">
                    <span>Vol. Total Bahan: <strong class="text-gray-900 dark:text-white">{{ $vol_bahan }}L</strong></span>
                    <span class="text-gray-300 dark:text-gray-700">|</span>
                    <span>Space Aman Wadah: <strong class="text-green-600 dark:text-green-400">{{ $space_aman }}L</strong></span>
                </div>

                <button type="button" wire:click="simpanBatch" class="w-full py-3 px-4 bg-green-600 hover:bg-green-700 active:scale-[0.99] text-white rounded-xl font-semibold shadow-lg shadow-green-600/20 transition duration-200 flex items-center justify-center gap-2">
                    <x-filament::icon icon="heroicon-o-check-circle" class="w-5 h-5" />
                    Simpan Batch ke Database
                </button>
            </div>
        </div>

        <!-- KOLOM KANAN: SOP INTERAKTIF & MILESTONE -->
        <div class="xl:col-span-5 space-y-6">
            <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-4 pb-4 border-b border-gray-100 dark:border-gray-800">
                    <div class="p-2.5 bg-green-50 dark:bg-green-950/50 text-green-600 dark:text-green-400 rounded-xl">
                        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Checklist SOP Batching</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Centang tahapan saat pengerjaan</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 cursor-pointer hover:bg-green-50/30 transition">
                        <input type="checkbox" wire:model.live="sop_steps.1" class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 w-4 h-4">
                        <div class="text-sm">
                            <strong class="block text-gray-900 dark:text-white [{{ $sop_steps[1] ? 'text-decoration:line-through; opacity:0.6;' : '' }}]">[1] Persiapan Wadah & Air</strong>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Bersihkan wadah dan isi air bersih sesuai takaran volume.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 cursor-pointer hover:bg-green-50/30 transition">
                        <input type="checkbox" wire:model.live="sop_steps.2" class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 w-4 h-4">
                        <div class="text-sm">
                            <strong class="block text-gray-900 dark:text-white [{{ $sop_steps[2] ? 'text-decoration:line-through; opacity:0.6;' : '' }}]">[2] Pencampuran Molase / Gula</strong>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Larutkan molase ke dalam air secara merata sempurna.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 cursor-pointer hover:bg-green-50/30 transition">
                        <input type="checkbox" wire:model.live="sop_steps.3" class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 w-4 h-4">
                        <div class="text-sm">
                            <strong class="block text-gray-900 dark:text-white [{{ $sop_steps[3] ? 'text-decoration:line-through; opacity:0.6;' : '' }}]">[3] Memasukkan Sampah Organik</strong>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Cincang sampah buah/sayur segar, lalu masukkan ke wadah.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 cursor-pointer hover:bg-green-50/30 transition">
                        <input type="checkbox" wire:model.live="sop_steps.4" class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 w-4 h-4">
                        <div class="text-sm">
                            <strong class="block text-gray-900 dark:text-white [{{ $sop_steps[4] ? 'text-decoration:line-through; opacity:0.6;' : '' }}]">[4] Pemasangan Sensor IoT</strong>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Pasang penutup wadah dengan rapat dan aktifkan sensor.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Estimasi Milestone -->
            <div class="p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100 dark:border-gray-800">
                    <div class="p-2.5 bg-green-50 dark:bg-green-950/50 text-green-600 dark:text-green-400 rounded-xl">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Estimasi Milestone</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Jadwal siklus fermentasi otomatis</p>
                    </div>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center p-3 rounded-xl bg-gray-50/50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400 font-medium">Hari 7 (Panen Gas)</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ date('d M Y', strtotime(($tanggal_mulai ?: date('Y-m-d')) . ' +7 days')) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-xl bg-gray-50/50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400 font-medium">Hari 30 (Cek Organik)</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ date('d M Y', strtotime(($tanggal_mulai ?: date('Y-m-d')) . ' +30 days')) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-xl bg-green-50/30 dark:bg-green-950/20 border border-green-100 dark:border-green-900/30">
                        <span class="text-green-700 dark:text-green-300 font-semibold">Hari 90 (Panen Utama)</span>
                        <span class="font-extrabold text-green-700 dark:text-green-300">{{ date('d M Y', strtotime(($tanggal_mulai ?: date('Y-m-d')) . ' +90 days')) }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>