<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\HealthLog;

class HealthCheckService
{
    public function __construct(
        private RestApiService $api,
        private AMIService     $ami
    ) {}

    /**
     * Run a full health check against MikoPBX.
     * Uses correct v3 endpoints for all checks.
     */
    public function check(): array
    {
        $amiOk  = false;
        $ariOk  = false;
        $sipOk  = false;
        $calls  = 0;
        $online = 0;

        // 1. AMI check — TCP socket connection on port 5038
        try {
            if ($this->ami->connect()) {
                $amiOk = true;
                $this->ami->disconnect();
            }
        } catch (\Throwable) {}

        // 2. REST API / ARI reachability check
        //    Use GET /pbxcore/api/v3/sysinfo:getInfo (correct v3 endpoint)
        try {
            $info  = $this->api->getSystemInfo();
            // result: true means the API key is valid and MikoPBX is responding
            $ariOk = ($info['result'] ?? false) === true;
        } catch (\Throwable) {}

        // 3. SIP trunk registration — GET /pbxcore/api/v3/sip-providers:getStatuses
        try {
            $trunk = $this->api->getTrunkStatus();
            $data  = $trunk['data'] ?? [];

            // Each item has: id, state (REGISTERED|UNREGISTERED|FAILED|...)
            $sipOk = collect($data)->contains(
                fn($t) => strtoupper($t['state'] ?? '') === 'REGISTERED'
            );
        } catch (\Throwable) {}

        // 4. Active calls count — GET /pbxcore/api/v3/pbx-status:getActiveCalls
        try {
            $active = $this->api->getActiveCalls();
            $data   = $active['data'] ?? [];
            $calls  = is_array($data) ? count($data) : 0;
        } catch (\Throwable) {}

        // 5. Online agent count (from local DB — extensions table)
        try {
            $online = \BitDreamIT\MikoPBX\Models\Extension::whereIn('status', ['online', 'busy'])->count();
        } catch (\Throwable) {}

        $status = match(true) {
            ! $amiOk && ! $ariOk => 'critical',
            ! $sipOk             => 'degraded',
            default              => 'healthy',
        };

        $result = compact('amiOk', 'ariOk', 'sipOk', 'calls', 'online', 'status');

        // Persist to DB
        HealthLog::create([
            'status'            => $status,
            'ami_connected'     => $amiOk,
            'ari_connected'     => $ariOk,
            'sip_trunk_up'      => $sipOk,
            'active_calls'      => $calls,
            'extensions_online' => $online,
            'details'           => $result,
            'checked_at'        => now(),
        ]);

        return $result;
    }

    public function latest(): ?HealthLog
    {
        return HealthLog::latest('checked_at')->first();
    }

    public function history(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return HealthLog::where('checked_at', '>=', now()->subHours($hours))
            ->orderBy('checked_at')
            ->get();
    }
}
