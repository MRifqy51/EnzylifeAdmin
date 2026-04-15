<div class="p-4 space-y-4">

    {{-- Card Kesehatan Sistem --}}
    <div class="bg-green-500/20 rounded-xl p-4 text-green-100 text-sm space-y-3">
        <div class="font-semibold text-white">Kesehatan Sistem</div>

        {{-- Cloud Sync --}}
        <div class="flex items-center gap-2">
            <x-heroicon-o-cloud class="w-4 h-4 text-green-300" />
            <span>Cloud Sync</span>
        </div>

        {{-- MicroSD --}}
        <div class="flex items-center gap-2">
            <x-heroicon-o-circle-stack class="w-4 h-4 text-green-300" />
            <span>MicroSD Backup</span>
        </div>
    </div>

    {{-- Logout --}}
    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
        @csrf

        <button type="submit"
            class="w-full flex items-center justify-center gap-2 
                   bg-red-500/20 hover:bg-red-500/30 
                   text-red-300 hover:text-white 
                   py-2 rounded-xl transition">

            {{-- Icon logout --}}
            <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />

            <span>Keluar</span>
        </button>
    </form>

</div>