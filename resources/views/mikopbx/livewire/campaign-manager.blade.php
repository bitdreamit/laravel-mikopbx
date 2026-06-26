<div class="bg-white rounded-xl shadow-sm border border-gray-100"
     wire:poll.{{ $pollInterval }}s="load">
    <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 text-sm">Campaigns</h3>
        <a href="{{ route('mikopbx.campaigns.create') }}" class="btn-primary text-xs px-3 py-1.5">+ New</a>
    </div>

    @if(empty($campaigns))
        <div class="px-4 py-8 text-center text-xs text-gray-400">
            No campaigns yet.
            <a href="{{ route('mikopbx.campaigns.create') }}" class="text-indigo-600 hover:underline">Create one</a>
        </div>
    @else
        <div class="divide-y divide-gray-50">
            @foreach($campaigns as $c)
            @php
                $badge = match($c['status'] ?? 'draft') {
                    'running'   => 'bg-green-100 text-green-800',
                    'paused'    => 'bg-yellow-100 text-yellow-800',
                    'completed' => 'bg-blue-100 text-blue-800',
                    'failed'    => 'bg-red-100 text-red-800',
                    default     => 'bg-gray-100 text-gray-600',
                };
                $bar = match($c['status'] ?? 'draft') {
                    'running'   => 'bg-green-500',
                    'completed' => 'bg-blue-500',
                    default     => 'bg-gray-400',
                };
                $progress = $c['progress'] ?? 0;
            @endphp
            <div class="px-4 py-3">
                {{-- Header row --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="badge {{ $badge }} text-xs">{{ ucfirst($c['status'] ?? 'draft') }}</span>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $c['name'] }}</p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0 ml-2">
                        @if(in_array($c['status'] ?? '', ['draft', 'paused']))
                            <button wire:click="start({{ $c['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="btn-success text-xs px-2 py-1">
                                ▶ Start
                            </button>
                        @endif
                        @if(($c['status'] ?? '') === 'running')
                            <button wire:click="pause({{ $c['id'] }})"
                                    class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-lg hover:bg-yellow-200">
                                ⏸
                            </button>
                            <button wire:click="stop({{ $c['id'] }})"
                                    wire:confirm="Stop this campaign?"
                                    class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-lg hover:bg-red-200">
                                ■
                            </button>
                        @endif
                        <a href="{{ route('mikopbx.campaigns.show', $c['id']) }}"
                           class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200">
                            Detail
                        </a>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 {{ $bar }}"
                             style="width: {{ $progress }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500 w-24 flex-shrink-0 text-right">
                        {{ $c['dialed'] ?? 0 }}/{{ $c['total'] ?? 0 }}
                        ({{ $progress }}%)
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
