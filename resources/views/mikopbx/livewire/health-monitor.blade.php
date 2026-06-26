<div class="space-y-6" wire:poll.60s="load">

    @php
        $status     = $result['status'] ?? 'unknown';
        $amiOk      = $result['amiOk']  ?? false;
        $ariOk      = $result['ariOk']  ?? false;
        $sipOk      = $result['sipOk']  ?? false;
        $calls      = $result['calls']  ?? 0;
        $online     = $result['online'] ?? 0;

        $bannerClass = match($status) {
            'healthy'  => 'bg-green-50 border border-green-200',
            'degraded' => 'bg-yellow-50 border border-yellow-200',
            default    => 'bg-red-50 border border-red-200',
        };
        $textClass = match($status) {
            'healthy'  => 'text-green-800',
            'degraded' => 'text-yellow-800',
            default    => 'text-red-800',
        };
        $icon = match($status) {
            'healthy'  => '✅',
            'degraded' => '⚠️',
            default    => '❌',
        };
    @endphp

    {{-- Status banner --}}
    <div class="rounded-xl p-5 flex items-center gap-4 {{ $bannerClass }}">
        <div class="text-4xl">{{ $icon }}</div>
        <div>
            <h2 class="text-lg font-bold {{ $textClass }}">
                MikoPBX is {{ ucfirst($status) }}
            </h2>
            <p class="text-sm opacity-75">
                Last checked: {{ now()->format('H:i:s') }} •
                Auto-refresh every 60s
            </p>
        </div>
        <div class="ml-auto">
            <button wire:click="runCheck"
                    wire:loading.attr="disabled"
                    class="btn-primary">
                <span wire:loading.remove>🔄 Run Check Now</span>
                <span wire:loading>Checking…</span>
            </button>
        </div>
    </div>

    {{-- Component cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach([
            ['AMI Connection',  $amiOk, 'Port 5038 — live events & call control via AMI'],
            ['REST API',        $ariOk, 'REST API v3 responding — CDR, extensions, trunks'],
            ['SIP Trunk',       $sipOk, 'AMARIP SIP trunk registered & ready for calls'],
            ['Overall',         $status === 'healthy', 'All systems operational'],
        ] as [$label, $ok, $desc])
        @php
            $cardBadge = $ok
                ? 'bg-green-100 text-green-700'
                : 'bg-red-100 text-red-700';
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-start justify-between mb-3">
                <span class="text-2xl">{{ $ok ? '✅' : '❌' }}</span>
                <span class="badge {{ $cardBadge }}">
                    {{ $ok ? 'Online' : 'Offline' }}
                </span>
            </div>
            <p class="font-semibold text-gray-900 text-sm">{{ $label }}</p>
            <p class="text-xs text-gray-400 mt-1 leading-snug">{{ $desc }}</p>
        </div>
        @endforeach
    </div>

    {{-- Live stats --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="stat-card">
            <p class="text-xs text-gray-400">Active Calls Right Now</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">{{ $calls }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-400">Extensions Online</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $online }}</p>
        </div>
    </div>

    {{-- Config summary --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-900 text-sm mb-4">Current Configuration</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            @foreach([
                ['MikoPBX URL',    config('mikopbx.url')],
                ['AMI Host',       config('mikopbx.ami.host').':'.config('mikopbx.ami.port')],
                ['ARI URL',        config('mikopbx.ari.url')],
                ['Route Prefix',   '/'.config('mikopbx.route_prefix')],
                ['Table Prefix',   config('mikopbx.table_prefix')],
                ['Dialer',         config('mikopbx.dialer.enabled') ? 'Enabled' : 'Disabled'],
            ] as [$key, $val])
            <div>
                <p class="text-xs text-gray-400">{{ $key }}</p>
                <p class="font-mono text-xs text-gray-700 truncate">{{ $val }}</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
