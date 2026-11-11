
<x-filament-panels::page>
    <form wire:submit.prevent="save"
          x-data="{ isAkunTab: window.location.href.includes('akun-pengguna') }"
          @click.window="isAkunTab = window.location.href.includes('akun-pengguna')">

        {{ $this->form }}

        {{-- Tombol hanya muncul jika URL mengandung 'akun-pengguna' --}}
        <div class="mt-6 flex justify-start" x-show="isAkunTab" style="display: none;">
            <x-filament::button type="submit">
                Simpan Perubahan Akun
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
