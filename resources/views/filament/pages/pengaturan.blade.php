<x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-start">
            <x-filament::button type="submit">
                Simpan Semua Pengaturan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>