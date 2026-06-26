<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
     wire:poll.15s>
    <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900 text-sm">Pending Callbacks</h3>
        <span class="badge bg-orange-100 text-orange-700">{{ $callbacks->total() }} pending</span>
    </div>

    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-4 py-2 text-left">Contact</th>
                <th class="px-4 py-2 text-left">Priority</th>
                <th class="px-4 py-2 text-left">Scheduled</th>
                <th class="px-4 py-2 text-left">Note</th>
                <th class="px-4 py-2 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($callbacks as $cb)
            {{-- FIX: @match inline replaced with @php variable --}}
            @php
                $priorityBadge = match($cb->priority) {
                    'urgent' => 'bg-red-100 text-red-800',
                    'high'   => 'bg-orange-100 text-orange-800',
                    'normal' => 'bg-blue-100 text-blue-800',
                    default  => 'bg-gray-100 text-gray-600',
                };
                $isOverdue = $cb->scheduled_at && $cb->scheduled_at->isPast();
            @endphp
            <tr class="table-row">
                <td class="px-4 py-2">
                    <p class="text-sm font-medium text-gray-900">{{ $cb->name ?? $cb->number }}</p>
                    <p class="text-xs font-mono text-gray-400">{{ $cb->number }}</p>
                </td>
                <td class="px-4 py-2">
                    <span class="badge {{ $priorityBadge }}">{{ ucfirst($cb->priority) }}</span>
                </td>
                <td class="px-4 py-2 text-xs text-gray-500">
                    {{ $cb->scheduled_at ? $cb->scheduled_at->format('d M H:i') : 'ASAP' }}
                    @if($isOverdue)
                        <span class="text-red-500 font-medium"> (overdue)</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-xs text-gray-500 max-w-xs truncate">
                    {{ $cb->note ?? '—' }}
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <button wire:click="attempt({{ $cb->id }})"
                                wire:loading.attr="disabled"
                                class="btn-success text-xs px-2 py-1">
                            <span wire:loading.remove wire:target="attempt({{ $cb->id }})">📞 Call</span>
                            <span wire:loading wire:target="attempt({{ $cb->id }})">…</span>
                        </button>
                        <button wire:click="cancel({{ $cb->id }})"
                                wire:confirm="Cancel this callback?"
                                class="text-xs text-gray-400 hover:text-red-500 px-2 py-1 rounded hover:bg-red-50 transition-colors">
                            ✕
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">
                    No pending callbacks 🎉
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($callbacks->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">
        {{ $callbacks->links() }}
    </div>
    @endif
</div>
