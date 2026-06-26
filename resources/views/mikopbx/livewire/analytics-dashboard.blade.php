<div class="space-y-6">

    {{-- Date filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">From</label>
                <input wire:model.live="from" type="date" class="input text-sm w-40">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500">To</label>
                <input wire:model.live="to" type="date" class="input text-sm w-40">
            </div>
            <div class="flex gap-2 ml-auto">
                <button wire:click="$set('from', '{{ now()->subDays(7)->format('Y-m-d') }}')"
                        class="btn-secondary text-xs">Last 7 days</button>
                <button wire:click="$set('from', '{{ now()->subDays(30)->format('Y-m-d') }}')"
                        class="btn-secondary text-xs">Last 30 days</button>
                <button wire:click="$set('from', '{{ now()->subDays(90)->format('Y-m-d') }}')"
                        class="btn-secondary text-xs">Last 90 days</button>
            </div>
        </div>
    </div>

    {{-- KPI Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
        @php
            $kpis = [
                ['Total',    $summary['total_calls']  ?? 0,                             'indigo'],
                ['Answered', $summary['answered']      ?? 0,                             'green'],
                ['Missed',   $summary['missed']        ?? 0,                             'red'],
                ['Failed',   $summary['failed']        ?? 0,                             'orange'],
                ['ASR',      ($summary['asr']           ?? 0).'%',                       'blue'],
                ['Avg Dur',  gmdate('i:s', $summary['avg_duration'] ?? 0),               'purple'],
                ['Inbound',  $summary['inbound']       ?? 0,                             'teal'],
            ];
        @endphp
        @foreach($kpis as [$label, $val, $color])
        <div class="stat-card text-center">
            <p class="text-xs text-gray-400">{{ $label }}</p>
            <p class="text-xl font-bold text-{{ $color }}-600 mt-1">{{ $val }}</p>
        </div>
        @endforeach
    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- Daily trend line chart --}}
        <div class="col-span-12 lg:col-span-8 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-4">Daily Call Trend</h3>
            <canvas id="mikopbx-daily-chart" height="80"></canvas>
        </div>

        {{-- Status doughnut --}}
        <div class="col-span-12 lg:col-span-4 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-4">By Status</h3>
            <canvas id="mikopbx-status-chart" height="160"></canvas>
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

    {{-- Peak Hours bar chart --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 text-sm mb-4">Peak Hours</h3>
        <canvas id="mikopbx-peak-chart" height="50"></canvas>
    </div>

    {{-- Agent Performance table --}}
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
                    @php
                        $asr = ($agent['total'] ?? 0) > 0
                            ? round(($agent['answered'] ?? 0) / $agent['total'] * 100, 1)
                            : 0;
                        $asrClass = $asr >= 80
                            ? 'text-green-600'
                            : ($asr >= 50 ? 'text-yellow-600' : 'text-red-500');
                    @endphp
                    <tr class="table-row">
                        <td class="px-4 py-2 font-mono font-medium text-gray-900">
                            {{ $agent['extension'] }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-700">{{ $agent['total'] ?? 0 }}</td>
                        <td class="px-4 py-2 text-right text-green-600 font-medium">{{ $agent['answered'] ?? 0 }}</td>
                        <td class="px-4 py-2 text-right">
                            <span class="font-medium {{ $asrClass }}">{{ $asr }}%</span>
                        </td>
                        <td class="px-4 py-2 text-right text-gray-600 font-mono text-xs">
                            {{ gmdate('i:s', $agent['avg_duration'] ?? 0) }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-600 font-mono text-xs">
                            {{ gmdate('i:s', $agent['longest'] ?? 0) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
                            No agent data for this period
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{--
    Chart.js initialisation.
    We use wire:init instead of x-init so it fires after Livewire renders,
    and we guard with a flag so it only runs once even on Livewire refreshes.
--}}
<script>
(function () {
    // Chart instances — store so we can destroy on re-render
    let dailyChart, statusChart, peakChart;

    function destroyCharts() {
        [dailyChart, statusChart, peakChart].forEach(c => { if (c) c.destroy(); });
    }

    function buildCharts() {
        destroyCharts();

        const daily     = @json($daily);
        const peakHours = @json($peakHours);
        const byStatus  = @json($byStatus);

        // ── Daily trend ─────────────────────────────────────────────────
        const dCtx = document.getElementById('mikopbx-daily-chart');
        if (dCtx) {
            dailyChart = new Chart(dCtx, {
                type: 'line',
                data: {
                    labels: daily.map(d => d.date),
                    datasets: [
                        {
                            label:           'Total',
                            data:            daily.map(d => d.total),
                            borderColor:     '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.1)',
                            tension:         0.3,
                            fill:            true,
                        },
                        {
                            label:           'Answered',
                            data:            daily.map(d => d.answered),
                            borderColor:     '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.1)',
                            tension:         0.3,
                            fill:            true,
                        },
                        {
                            label:           'Missed',
                            data:            daily.map(d => d.missed),
                            borderColor:     '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.05)',
                            tension:         0.3,
                            fill:            false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins:    { legend: { position: 'top' } },
                    scales:     { y: { beginAtZero: true } },
                },
            });
        }

        // ── Status doughnut ──────────────────────────────────────────────
        const sCtx = document.getElementById('mikopbx-status-chart');
        if (sCtx) {
            const colorMap = {
                answered: '#22c55e',
                missed:   '#ef4444',
                busy:     '#f97316',
                failed:   '#dc2626',
                ended:    '#6b7280',
            };
            statusChart = new Chart(sCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(byStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                    datasets: [{
                        data:            Object.values(byStatus),
                        backgroundColor: Object.keys(byStatus).map(s => colorMap[s] ?? '#9ca3af'),
                    }],
                },
                options: {
                    responsive: true,
                    plugins:    { legend: { position: 'bottom', labels: { boxWidth: 10 } } },
                },
            });
        }

        // ── Peak hours bar ───────────────────────────────────────────────
        const pCtx = document.getElementById('mikopbx-peak-chart');
        if (pCtx) {
            const hours = Array.from({ length: 24 }, (_, i) => i);
            peakChart = new Chart(pCtx, {
                type: 'bar',
                data: {
                    labels: hours.map(h => h + ':00'),
                    datasets: [{
                        label:           'Calls',
                        data:            hours.map(h => peakHours[h] ?? 0),
                        backgroundColor: '#6366f1',
                        borderRadius:    4,
                    }],
                },
                options: {
                    responsive: true,
                    plugins:    { legend: { display: false } },
                    scales:     { y: { beginAtZero: true } },
                },
            });
        }
    }

    // Run on initial load
    document.addEventListener('DOMContentLoaded', buildCharts);

    // Re-run after every Livewire update (date filter change)
    document.addEventListener('livewire:update', buildCharts);
})();
</script>
