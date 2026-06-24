<div class="bg-white rounded-xl shadow-sm border border-gray-100" wire:poll.{{ $pollInterval }}s="refresh">
    <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 text-sm flex items-center gap-2">
            <span class="w-2 h-2 bg-green-400 rounded-full pulse-green"></span>
            Live Calls
        </h3>
        <span class="badge bg-gray-100 text-gray-600">{{ count($activeCalls) }} active</span>
    </div>

    @if(count($activeCalls) === 0)
        <div class="px-4 py-8 text-center text-xs text-gray-400">No active calls right now</div>
    @else
        <div class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
            @foreach($activeCalls as $call)
            <div class="px-4 py-3 flex items-center gap-3">
                {{-- Status indicator --}}
                <span class="w-2 h-2 rounded-full bg-green-400 pulse-green flex-shrink-0"></span>

                {{-- Call info --}}
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-900 truncate">
                        {{ $call['src'] ?? $call['caller'] ?? 'Unknown' }}
                        <span class="text-gray-400 font-normal">→</span>
                        {{ $call['dst'] ?? $call['extension'] ?? '—' }}
                    </p>
                    <p class="text-xs text-gray-400">
                        {{ $call['duration'] ?? '0:00' }} •
                        {{ $call['state'] ?? $call['status'] ?? 'Active' }}
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1 flex-shrink-0">
                    {{-- Transfer --}}
                    <button wire:click="openTransfer('{{ $call['channel'] ?? '' }}')"
                            class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors"
                            title="Transfer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </button>
                    {{-- Hangup --}}
                    <button wire:click="hangup('{{ $call['channel'] ?? '' }}')"
                            class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                            title="Hangup">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 006.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 011.767-1.052l3.223.716A1.5 1.5 0 0118 15.352V16.5a1.5 1.5 0 01-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 012.43 8.326 13.019 13.019 0 012 5V3.5z"/>
                        </svg>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- Transfer Modal --}}
    @if($showTransfer)
    <div class="px-4 py-3 border-t border-gray-100 bg-blue-50">
        <p class="text-xs font-medium text-blue-800 mb-2">Transfer to extension:</p>
        <div class="flex gap-2">
            <input wire:model="transferTo" placeholder="e.g. 102"
                   class="flex-1 text-xs border border-blue-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
            <button wire:click="doTransfer" class="btn-primary text-xs px-3 py-1.5">Transfer</button>
            <button wire:click="$set('showTransfer', false)" class="btn-secondary text-xs px-3 py-1.5">✕</button>
        </div>
    </div>
    @endif
</div>
