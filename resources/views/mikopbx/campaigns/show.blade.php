@extends('mikopbx::layouts.app')
@section('title', $campaign->name)
@section('heading', $campaign->name)

@section('content')
<div class="space-y-6" x-data="campaignDetail({{ $campaign->id }}, '{{ $campaign->status }}')">

    <div class="flex items-center gap-3">
        <a href="{{ route('mikopbx.campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Campaigns</a>
        <span class="badge {{ $campaign->status_badge }}">{{ ucfirst($campaign->status) }}</span>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        @foreach([
            ['Total',    $stats['total'],    'gray'],
            ['Dialed',   $stats['dialed'],   'blue'],
            ['Answered', $stats['answered'], 'green'],
            ['Failed',   $stats['failed'],   'red'],
            ['Pending',  $stats['pending'],  'yellow'],
            ['ASR',      $stats['asr'].'%',  'purple'],
        ] as [$label, $val, $color])
        <div class="stat-card text-center">
            <p class="text-xs text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold text-{{ $color }}-600 mt-1">{{ $val }}</p>
        </div>
        @endforeach
    </div>

    {{-- Progress --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex justify-between text-sm mb-2">
            <span class="font-medium text-gray-700">Progress</span>
            <span class="text-gray-500" x-text="progress + '%'">{{ $stats['progress'] }}%</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-700
                {{ $campaign->status === 'completed' ? 'bg-blue-500' : ($campaign->status === 'running' ? 'bg-green-500' : 'bg-indigo-400') }}"
                 :style="'width:' + progress + '%'"></div>
        </div>
    </div>

    {{-- Campaign actions --}}
    <div class="flex items-center gap-3">
        @if(in_array($campaign->status, ['draft','paused']))
            <form method="POST" action="{{ route('mikopbx.campaigns.start', $campaign) }}">
                @csrf
                <button type="submit" class="btn-primary">▶ Start Campaign</button>
            </form>
        @endif
        @if($campaign->status === 'running')
            <form method="POST" action="{{ route('mikopbx.campaigns.pause', $campaign) }}">
                @csrf
                <button type="submit" class="btn-secondary">⏸ Pause</button>
            </form>
            <form method="POST" action="{{ route('mikopbx.campaigns.stop', $campaign) }}"
                  onsubmit="return confirm('Stop this campaign permanently?')">
                @csrf
                <button type="submit" class="btn-danger">■ Stop</button>
            </form>
        @endif

        <div class="ml-auto flex items-center gap-2 text-xs text-gray-400">
            <span>Max channels: {{ $campaign->max_channels }}</span>
            <span>•</span>
            <span>Retries: {{ $campaign->retry_attempts }}</span>
            @if($campaign->started_at)
                <span>•</span>
                <span>Started {{ $campaign->started_at->diffForHumans() }}</span>
            @endif
        </div>
    </div>

    {{-- Number list --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 text-sm">Number List</h3>
            <span class="text-xs text-gray-400">{{ $campaign->total_numbers }} numbers</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Number</th>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Attempts</th>
                        <th class="px-4 py-2 text-left">DTMF</th>
                        <th class="px-4 py-2 text-left">Last Tried</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($numbers as $num)
                    <tr class="table-row">
                        <td class="px-4 py-2 font-mono text-xs font-medium">{{ $num->number }}</td>
                        <td class="px-4 py-2 text-xs text-gray-600">{{ $num->name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="badge text-xs
                                @match($num->status)
                                    @case('answered')  bg-green-50 text-green-700 @break
                                    @case('no_answer') bg-yellow-50 text-yellow-700 @break
                                    @case('busy')      bg-orange-50 text-orange-700 @break
                                    @case('failed')    bg-red-50 text-red-700 @break
                                    @case('opted_out') bg-gray-100 text-gray-600 @break
                                    @case('dialing')   bg-blue-50 text-blue-700 @break
                                    @default           bg-gray-50 text-gray-500
                                @endmatch">
                                {{ ucfirst(str_replace('_',' ',$num->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-600">{{ $num->attempt }}</td>
                        <td class="px-4 py-2 text-xs text-gray-600">{{ $num->dtmf_response ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-gray-400">{{ $num->last_attempted_at?->diffForHumans() ?? 'Not yet' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No numbers yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $numbers->links() }}</div>
    </div>
</div>

<script>
function campaignDetail(id, status) {
    return {
        progress: {{ $stats['progress'] }},
        status: status,
        init() {
            if (this.status === 'running') {
                setInterval(() => this.syncProgress(), 8000);
            }
        },
        async syncProgress() {
            try {
                const r = await fetch(`{{ route('mikopbx.campaigns.progress', '') }}/${id}`);
                const d = await r.json();
                if (d.progress !== undefined) this.progress = d.progress;
            } catch {}
        }
    };
}
</script>
@endsection
