<div class="space-y-4">

    {{-- Add form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 text-sm mb-4">Block a Number</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input wire:model="number" placeholder="Number e.g. 01711000000"
                   class="input md:col-span-1">
            <input wire:model="reason" placeholder="Reason (optional)"
                   class="input">
            <select wire:model="direction" class="input">
                <option value="both">Both (block all)</option>
                <option value="inbound">Inbound only</option>
                <option value="outbound">Outbound only</option>
            </select>
            <button wire:click="add" class="btn-danger justify-center">🚫 Add to Blacklist</button>
        </div>
        @error('number')<p class="text-xs text-red-500 mt-2">{{ $message }}</p>@enderror
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 text-sm">Blocked Numbers</h3>
            <input wire:model.live="search" placeholder="Search…" class="input text-xs w-48">
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Number</th>
                    <th class="px-4 py-2 text-left">Direction</th>
                    <th class="px-4 py-2 text-left">Reason</th>
                    <th class="px-4 py-2 text-left">Expires</th>
                    <th class="px-4 py-2 text-left">Added</th>
                    <th class="px-4 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($list as $item)
                <tr class="table-row">
                    <td class="px-4 py-2 font-mono font-medium text-gray-900 text-sm">{{ $item->number }}</td>
                    <td class="px-4 py-2">
                        <span class="badge bg-red-50 text-red-700">{{ ucfirst($item->direction) }}</span>
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-600">{{ $item->reason ?? '—' }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500">{{ $item->expires_at?->format('d M Y') ?? 'Never' }}</td>
                    <td class="px-4 py-2 text-xs text-gray-400">{{ $item->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-2">
                        <button wire:click="remove('{{ $item->number }}')"
                                wire:confirm="Remove {{ $item->number }} from blacklist?"
                                class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded-lg transition-colors">
                            Remove
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No blocked numbers</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $list->links() }}</div>
    </div>
</div>
