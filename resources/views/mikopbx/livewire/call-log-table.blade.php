<div>
    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <input wire:model.live.debounce.300ms="search"
                   placeholder="Search number…"
                   class="input col-span-2 md:col-span-1">

            <select wire:model.live="status" class="input">
                <option value="">All Status</option>
                <option value="answered">Answered</option>
                <option value="missed">Missed</option>
                <option value="busy">Busy</option>
                <option value="failed">Failed</option>
                <option value="ended">Ended</option>
            </select>

            <select wire:model.live="direction" class="input">
                <option value="">All Directions</option>
                <option value="inbound">Inbound</option>
                <option value="outbound">Outbound</option>
                <option value="internal">Internal</option>
            </select>

            <input wire:model.live="date" type="date" class="input">

            <button wire:click="$set('search',''); $set('status',''); $set('direction',''); $set('date','')"
                    class="btn-secondary text-xs">
                Clear
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Caller</th>
                        <th class="px-4 py-3 text-left">Extension</th>
                        <th class="px-4 py-3 text-left">Direction</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Duration</th>
                        <th class="px-4 py-3 text-left">Time</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($calls as $call)
                    @php
                        $dirBadge = match($call->direction) {
                            'inbound'  => 'bg-blue-50 text-blue-700',
                            'outbound' => 'bg-purple-50 text-purple-700',
                            default    => 'bg-gray-100 text-gray-600',
                        };
                        $statusBadge = match($call->status) {
                            'answered'    => 'bg-green-50 text-green-700',
                            'missed'      => 'bg-red-50 text-red-700',
                            'busy'        => 'bg-orange-50 text-orange-700',
                            'failed'      => 'bg-red-100 text-red-800',
                            default       => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="table-row">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-gray-900">
                            {{ $call->caller }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ $call->extension ?: '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $dirBadge }}">
                                {{ ucfirst($call->direction) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $statusBadge }}">
                                {{ ucfirst($call->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 font-mono">
                            {{ $call->duration_formatted }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">
                            {{ $call->started_at?->format('d M H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if($call->recording_file)
                                    <a href="{{ route('mikopbx.recordings.play', ['filename' => $call->recording_file]) }}"
                                       target="_blank"
                                       class="text-xs text-indigo-600 hover:text-indigo-800"
                                       title="Play recording">▶</a>
                                @endif
                                <button onclick="window.mikopbxDial && window.mikopbxDial('{{ $call->caller }}')"
                                        class="text-xs text-green-600 hover:text-green-800"
                                        title="Call back">📞</button>
                                <a href="{{ route('mikopbx.calls.show', $call) }}"
                                   class="text-xs text-gray-400 hover:text-gray-600">Detail</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">
                            No calls found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $calls->links() }}
        </div>
    </div>
</div>
