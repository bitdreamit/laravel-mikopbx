<div class="space-y-6" x-data x-init="initCharts(@js($daily), @js($peakHours), @js($byStatus))">

    {{-- Date filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">From</label>
                <input wire:model.live="from" type="date" class="input text-sm w-38">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">To</label>
                <input wire:model.live="to" type="date" class="input text-sm w-38">
            </div>
            <div class="flex gap-2 ml-auto">
                @foreach(['7' => 'Last 7 days','30' => 'Last 30 days','90' => 'Last 90 days'] as $days => $label)
                <button wire:click="$set('from', '{{ now()->subDays($days)->format('Y-m-d') }}')"
                        class="btn-secondary text-xs">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- KPI Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
        @foreach([
            ['Total',     $summary['total_calls']  ?? 0, 'indigo'],
            ['Answered',  $summary['answered']      ?? 0, 'green'],
            ['Missed',    $summary['missed']        ?? 0, 'red'],
            ['Failed',    $summary['failed']        ?? 0, 'orange'],
            ['ASR',       ($summary['asr'] ?? 0).'%','blue'],
            ['Avg Dur',   gmdate('i:s', $summary['avg_duration'] ?? 0), 'purple'],
            ['Inbound',   $summary['inbound']       ?? 0, 'teal'],
        ] as [$label, $val, $color])
        <div class="stat-card text-center">
            <p class="text-xs text-gray-400">{{ $label }}</p>
            <p class="text-xl font-bold text-{{ $color }}-600 mt-1">{{ $val }}</p>
        </div>
        @endforeach
    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- Daily trend --}}
        <div class="col-span-12 lg:col-span-8 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-4">Daily Call Trend</h3>
            <canvas id="dailyChart" height="80"></canvas>
        </div>

        {{-- Status doughnut --}}
        <div class="col-span-12 lg:col-span-4 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-4">By Status</h3>
            <canvas id="statusChart" height="160"></canvas>
            <div class="mt-4 space-y-1">
                @foreach($byStatus as $status => $count)
                <div class="flex justify-between text-xs">
                    <span class="text-gray-600">{{ ucfirst($status) }}</span>
                    <span class="font-semibold text-gray-900">{{ $count }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Peak Hours --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 text-sm mb-4">Peak Hours</h3>
        <canvas id="peakChart" height="50"></canvas>
    </div>

    {{-- Agent Performance --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-5 py-3">
            <h3 class="font-semibold text-gray-900 text-sm">Agent Performance</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Extension</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-right">Answered</th>
                        <th class="px-4 py-2 text-right">ASR %</th>
                        <th class="px-4 py-2 text-right">Avg Duration</th>
                        <th class="px-4 py-2 text-right">Longest</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($agents as $agent)
                    <tr class="table-row">
                        <td class="px-4 py-2 font-mono font-medium text-gray-900">{{ $agent['extension'] }}</td>
                        <td class="px-4 py-2 text-right text-gray-700">{{ $agent['total'] }}</td>
                        <td class="px-4 py-2 text-right text-green-600 font-medium">{{ $agent['answered'] }}</td>
                        <td class="px-4 py-2 text-right">
                            @php $asr = $agent['total'] > 0 ? round($agent['answered']/$agent['total']*100,1) : 0 @endphp
                            <span class="font-medium {{ $asr >= 80 ? 'text-green-600' : ($asr >= 50 ? 'text-yellow-600' : 'text-red-500') }}">
                                {{ $asr }}%
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-gray-600 font-mono text-xs">{{ gmdate('i:s', $agent['avg_duration'] ?? 0) }}</td>
                        <td class="px-4 py-2 text-right text-gray-600 font-mono text-xs">{{ gmdate('i:s', $agent['longest'] ?? 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No agent data for this period</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function initCharts(daily, peakHours, byStatus) {
    // Daily trend
    const dailyLabels = daily.map(d => d.date);
    const dailyTotal  = daily.map(d => d.total);
    const dailyAns    = daily.map(d => d.answered);
    const dailyMissed = daily.map(d => d.missed);

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [
                { label:'Total',    data: dailyTotal,  borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,0.1)', tension:.3, fill:true },
                { label:'Answered', data: dailyAns,    borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,0.1)',  tension:.3, fill:true },
                { label:'Missed',   data: dailyMissed, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.05)', tension:.3, fill:false },
            ]
        },
        options: { responsive:true, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true}} }
    });

    // Status doughnut
    const statusColors = { answered:'#22c55e', missed:'#ef4444', busy:'#f97316', failed:'#dc2626', ended:'#6b7280' };
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(byStatus).map(s => s.charAt(0).toUpperCase()+s.slice(1)),
            datasets: [{ data: Object.values(byStatus), backgroundColor: Object.keys(byStatus).map(s=>statusColors[s]||'#9ca3af') }]
        },
        options: { responsive:true, plugins:{legend:{position:'bottom', labels:{boxWidth:10}}} }
    });

    // Peak hours bar
    const hours = Array.from({length:24}, (_,i) => i);
    new Chart(document.getElementById('peakChart'), {
        type: 'bar',
        data: {
            labels: hours.map(h => h+':00'),
            datasets: [{ label:'Calls', data: hours.map(h => peakHours[h]||0), backgroundColor:'#6366f1', borderRadius:4 }]
        },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });
}
</script>
