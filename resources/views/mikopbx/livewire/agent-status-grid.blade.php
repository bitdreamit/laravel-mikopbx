<div class="bg-white rounded-xl shadow-sm border border-gray-100" wire:poll.{{ $pollInterval }}s="load">
    <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 text-sm">Agent Status</h3>
        <div class="flex items-center gap-3 text-xs text-gray-400">
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-400 rounded-full"></span> Online</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-orange-400 rounded-full"></span> Busy</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-gray-300 rounded-full"></span> Offline</span>
        </div>
    </div>

    @if(empty($agents))
        <div class="px-4 py-6 text-center text-xs text-gray-400">
            No agents synced yet.
            <a href="{{ route('mikopbx.agents.sync') }}"
               onclick="event.preventDefault(); fetch(this.href,{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}}).then(()=>location.reload())"
               class="text-indigo-600 hover:underline">Sync now</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-0 divide-y divide-gray-50 max-h-52 overflow-y-auto">
            @foreach($agents as $agent)
            <div class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 group">
                <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $agent['dot'] }}"
                      :class="'{{ $agent['dot'] }}'"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-900 truncate">{{ $agent['name'] }}</p>
                    <p class="text-xs text-gray-400">Ext {{ $agent['extension'] }}</p>
                </div>
                <button wire:click="call('{{ $agent['extension'] }}')"
                        class="opacity-0 group-hover:opacity-100 p-1 text-green-600 hover:text-green-800 transition-opacity"
                        title="Call {{ $agent['extension'] }}">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                    </svg>
                </button>
            </div>
            @endforeach
        </div>
    @endif
</div>
