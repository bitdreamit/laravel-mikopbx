@if($visible)
<div class="fixed top-4 left-1/2 -translate-x-1/2 z-50 w-80"
     x-data x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 -translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0">
    <div class="bg-white rounded-2xl shadow-2xl border-2 border-green-400 overflow-hidden">

        {{-- Header bar --}}
        <div class="bg-green-500 px-4 py-2 flex items-center gap-2">
            <span class="w-2 h-2 bg-white rounded-full pulse-green"></span>
            <span class="text-white text-xs font-semibold">Incoming Call</span>
            <span class="ml-auto text-green-100 text-xs">Ext {{ $extension }}</span>
        </div>

        {{-- Caller info --}}
        <div class="px-5 py-4 flex items-center gap-4">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <div>
                <p class="text-lg font-bold text-gray-900 font-mono tracking-wide">{{ $caller }}</p>
                <p class="text-xs text-gray-400">Calling extension {{ $extension }}</p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="px-4 pb-4 grid grid-cols-3 gap-2">
            <button wire:click="answer"
                    class="flex flex-col items-center gap-1 p-3 bg-green-50 hover:bg-green-100 rounded-xl transition-colors">
                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                </svg>
                <span class="text-xs text-green-700 font-medium">Answer</span>
            </button>

            <button wire:click="logCall"
                    class="flex flex-col items-center gap-1 p-3 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span class="text-xs text-blue-700 font-medium">Log</span>
            </button>

            <button wire:click="reject"
                    class="flex flex-col items-center gap-1 p-3 bg-red-50 hover:bg-red-100 rounded-xl transition-colors">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="text-xs text-red-700 font-medium">Reject</span>
            </button>
        </div>
    </div>
</div>
@endif
