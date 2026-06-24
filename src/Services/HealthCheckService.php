<?php
namespace BitDreamIT\MikoPBX\Services;
use BitDreamIT\MikoPBX\Models\HealthLog;

class HealthCheckService
{
    public function __construct(
        private RestApiService $api,
        private AMIService $ami
    ) {}

    public function check(): array
    {
        $amiOk  = false;
        $ariOk  = false;
        $sipOk  = false;
        $calls  = 0;
        $online = 0;

        try {
            if ($this->ami->connect()) {
                $amiOk = true;
                $this->ami->disconnect();
            }
        } catch (\Throwable) {}

        try {
            $info = $this->api->getSystemInfo();
            $ariOk = isset($info['data']);
        } catch (\Throwable) {}

        try {
            $trunk = $this->api->getTrunkStatus();
            $sipOk = collect($trunk['data'] ?? [])->contains(fn($t) => ($t['state'] ?? '') === 'Registered');
        } catch (\Throwable) {}

        try {
            $active = $this->api->getActiveCalls();
            $calls  = count($active['data'] ?? []);
        } catch (\Throwable) {}

        $status = match(true) {
            ! $amiOk && ! $ariOk => 'critical',
            ! $sipOk             => 'degraded',
            default              => 'healthy',
        };

        $result = compact('amiOk', 'ariOk', 'sipOk', 'calls', 'online', 'status');

        HealthLog::create([
            'status'             => $status,
            'ami_connected'      => $amiOk,
            'ari_connected'      => $ariOk,
            'sip_trunk_up'       => $sipOk,
            'active_calls'       => $calls,
            'extensions_online'  => $online,
            'details'            => $result,
            'checked_at'         => now(),
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
