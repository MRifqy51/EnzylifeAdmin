<div class="p-4 space-y-4" wire:poll.5s="updateStatus">

    {{-- Card Kesehatan Sistem --}}
    <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-4 text-green-100 text-sm space-y-3">
        <div class="font-semibold text-white flex items-center gap-1.5">
            🔧 Kesehatan Sistem
        </div>

        {{-- Cloud Sync --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-cloud class="w-4 h-4 text-green-300" />
                <span>Cloud Sync</span>
            </div>
            <span class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-green-500/20 text-green-300 font-mono text-[10px] uppercase font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> Active
            </span>
        </div>

        {{-- MicroSD Backup --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-circle-stack class="w-4 h-4 text-green-300" />
                <span>MicroSD Backup</span>
            </div>
            
            @if($sdStatus === 'CONNECTED')
                <span class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-green-500/20 text-green-400 font-mono text-[10px] uppercase font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-50 animate-ping"></span> CONNECTED
                </span>
            @elseif($sdStatus === 'EJECTED')
                <span class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 font-mono text-[10px] uppercase font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> EJECTED
                </span>
            @else
                <span class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-red-500/20 text-red-400 font-mono text-[10px] uppercase font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span> DISCONNECTED
                </span>
            @endif
        </div>

        {{-- Tombol Aksi Eject Terintegrasi (Muncul Hanya Saat Connected) --}}
        @if($sdStatus === 'CONNECTED')
            <button 
                wire:click="ejectSDCard"
                wire:loading.attr="disabled"
                class="mt-2 w-full text-center bg-red-600/80 hover:bg-red-600 border border-red-500/30 transition active:scale-95 text-white font-semibold py-1.5 px-3 rounded-lg text-xs shadow-sm flex items-center justify-center gap-1"
            >
                <span wire:loading.remove>🛑 Eject MicroSD</span>
                <span wire:loading>🔄 Memproses...</span>
            </button>
        @endif
    </div>

    {{-- Logout Bawaan Asli Kamu --}}
    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
        @csrf

        <button type="submit"
            class="w-full flex items-center justify-center gap-2 
                   bg-red-500/10 hover:bg-red-500/20 border border-red-500/20
                   text-red-300 hover:text-white 
                   py-2 rounded-xl transition">

            {{-- Icon logout --}}
            <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />

            <span>Keluar</span>
        </button>
    </form>

</div>