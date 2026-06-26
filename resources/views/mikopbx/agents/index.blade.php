@extends('mikopbx::layouts.app')
@section('title','Agents')
@section('heading','Agents & Extensions')

@section('content')
<div class="space-y-4">

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $agents->count() }} agents</p>
        <form method="POST" action="{{ route('mikopbx.agents.sync') }}">
            @csrf
            <button type="submit" class="btn-secondary text-sm">🔄 Sync from MikoPBX</button>
        </form>
    </div>

    {{-- Status summary --}}
    <div class="grid grid-cols-4 gap-4">
        @php
            $onlineCount  = $agents->whereIn('status', ['online'])->count();
            $busyCount    = $agents->where('status', 'busy')->count();
            $dndCount     = $agents->where('status', 'dnd')->count();
            $offlineCount = $agents->where('status', 'offline')->count();
        @endphp
        <div class="stat-card text-center">
            <div class="w-3 h-3 bg-green-400 rounded-full mx-auto mb-2"></div>
            <p class="text-2xl font-bold text-gray-900">{{ $onlineCount }}</p>
            <p class="text-xs text-gray-500">Online</p>
        </div>
        <div class="stat-card text-center">
            <div class="w-3 h-3 bg-orange-400 rounded-full mx-auto mb-2"></div>
            <p class="text-2xl font-bold text-gray-900">{{ $busyCount }}</p>
            <p class="text-xs text-gray-500">Busy</p>
        </div>
        <div class="stat-card text-center">
            <div class="w-3 h-3 bg-red-400 rounded-full mx-auto mb-2"></div>
            <p class="text-2xl font-bold text-gray-900">{{ $dndCount }}</p>
            <p class="text-xs text-gray-500">DND</p>
        </div>
        <div class="stat-card text-center">
            <div class="w-3 h-3 bg-gray-300 rounded-full mx-auto mb-2"></div>
            <p class="text-2xl font-bold text-gray-900">{{ $offlineCount }}</p>
            <p class="text-xs text-gray-500">Offline</p>
        </div>
    </div>

    {{-- Agent table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Agent</th>
                    <th class="px-4 py-3 text-left">Extension</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Role</th>
                    <th class="px-4 py-3 text-left">Last Seen</th>
                    <th class="px-4 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($agents as $agent)
                @php
                    $dotColor = match($agent->status) {
                        'online'  => 'bg-green-400',
                        'busy'    => 'bg-orange-400',
                        'dnd'     => 'bg-red-400',
                        'away'    => 'bg-yellow-400',
                        default   => 'bg-gray-300',
                    };
                @endphp
                <tr class="table-row">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold flex-shrink-0">
                                {{ strtoupper(substr($agent->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 text-sm">{{ $agent->name }}</p>
                                <p class="text-xs text-gray-400">{{ $agent->email ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-mono text-sm font-semibold text-gray-700">
                        {{ $agent->extension }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ $dotColor }}"></span>
                            <span class="text-sm text-gray-700">{{ ucfirst($agent->status) }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="badge bg-gray-100 text-gray-600">{{ ucfirst($agent->role ?? 'agent') }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400">
                        {{ $agent->last_seen_at?->diffForHumans() ?? 'Never' }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            {{-- Click to call --}}
                            <button onclick="window.mikopbxDial && window.mikopbxDial('{{ $agent->extension }}')"
                                    class="p-1.5 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-lg transition-colors"
                                    title="Call {{ $agent->extension }}">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                </svg>
                            </button>
                            {{-- Status change — plain form, no Alpine needed --}}
                            <form method="POST"
                                  action="{{ route('mikopbx.agents.status') }}"
                                  onchange="this.submit()">
                                @csrf
                                <input type="hidden" name="extension" value="{{ $agent->extension }}">
                                <select name="status"
                                        class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none">
                                    @foreach(['online','offline','busy','dnd','away'] as $st)
                                        <option value="{{ $st }}" {{ $agent->status === $st ? 'selected' : '' }}>
                                            {{ ucfirst($st) }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">
                        No agents found.
                        <form method="POST" action="{{ route('mikopbx.agents.sync') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-indigo-600 hover:underline ml-1">
                                Sync from MikoPBX
                            </button>
                        </form>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
