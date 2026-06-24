<div class="bg-white rounded-xl shadow-sm border border-gray-100" wire:poll.{{ $pollInterval }}s="load">
    <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 text-sm">Campaigns</h3>
        <a href="{{ route('mikopbx.campaigns.create') }}" class="btn-primary text-xs px-3 py-1.5">+ New</a>
    </div>

    @if(empty($campaigns))
        <div class="px-4 py-8 text-center text-xs text-gray-400">
            No campaigns yet. <a href="{{ route('mikopbx.campaigns.create') }}" class="text-indigo-600 hover:underline">Create one</a>
        </div>
    @else
        <div class="divide-y divide-gray-50">
            @foreach($campaigns as $c)
            <div class="px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="badge {{ $c['status_badge'] ?? 'bg-gray-100 text-gray-600' }} text-xs">
                            {{ ucfirst($c['status']) }}
                        </span>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $c['name'] }}</p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                        @if($c['status'] === 'draft' || $c['status'] === 'paused')
                            <button wire:click="start({{ $c['id'] }})"
                                    class="btn-success text-xs px-2 py-1">▶ Start</button>
                        @endif
                        @if($c['status'] === 'running')
                            <button wire:click="pause({{ $c['id'] }})"
                                    class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-lg hover:bg-yellow-200">⏸</button>
                            <button wire:click="stop({{ $c['id'] }})"
                                    class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200">■ Stop</button>
                        @endif
                        <a href="{{ route('mikopbx.campaigns.show', $c['id']) }}"
                           class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200">Detail</a>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500
                            {{ $c['status'] === 'completed' ? 'bg-blue-500' : ($c['status'] === 'running' ? 'bg-green-500' : 'bg-gray-400') }}"
                             style="width: {{ $c['progress'] ?? 0 }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500 w-20 flex-shrink-0">
                        {{ $c['dialed'] ?? 0 }}/{{ $c['total'] ?? 0 }}
                        ({{ $c['progress'] ?? 0 }}%)
                    </span>
                </div>

                {{-- Stats row --}}
                <div class="flex gap-4 mt-1.5 text-xs text-gray-400">
                    <span class="text-green-600 font-medium">✓ {{ $c['answered'] ?? 0 }}</span>
                    <span class="text-red-400">✗ {{ $c['failed'] ?? 0 }}</span>
                    <span>ASR {{ $c['asr'] ?? 0 }}%</span>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
