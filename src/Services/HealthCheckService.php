<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Log;

/**
 * Health Check Service
 * Monitor MikoPBX system health, SIP trunk status, and AMI connectivity.
 */
class HealthCheckService
{
    public function __construct(
        private RestApiService $api,
        private AMIService     $ami,
    ) {}

    /** Full system health check */
    public function check(): array
    {
        $results = [
            'timestamp'   => now()->toISOString(),
            'overall'     => 'healthy',
            'checks'      => [],
        ];

        // REST API check
        try {
            $version = $this->api->getVersion();
            $results['checks']['rest_api'] = ['status' => 'ok', 'version' => $version['data'] ?? 'unknown'];
        } catch (\Throwable $e) {
            $results['checks']['rest_api'] = ['status' => 'error', 'message' => $e->getMessage()];
            $results['overall'] = 'degraded';
        }

        // AMI check
        try {
            $ping = $this->ami->connect()->ping();
            $results['checks']['ami'] = ['status' => $ping ? 'ok' : 'error'];
            $this->ami->disconnect();
        } catch (\Throwable $e) {
            $results['checks']['ami'] = ['status' => 'error', 'message' => $e->getMessage()];
            $results['overall'] = 'degraded';
        }

        // Active calls check
        try {
            $calls = $this->api->getActiveCalls();
            $results['checks']['active_calls'] = ['status' => 'ok', 'count' => count($calls['data'] ?? [])];
        } catch (\Throwable $e) {
            $results['checks']['active_calls'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Extension status check
        try {
            $exts   = $this->api->getExtensionStatuses();
            $online = collect($exts['data'] ?? [])->where('status', 'REGISTERED')->count();
            $total  = count($exts['data'] ?? []);
            $results['checks']['extensions'] = ['status' => 'ok', 'online' => $online, 'total' => $total];
        } catch (\Throwable $e) {
            $results['checks']['extensions'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Mark unhealthy if all critical checks fail
        $failed = collect($results['checks'])->where('status', 'error')->count();
        if ($failed >= 2) $results['overall'] = 'unhealthy';

        return $results;
    }

    /** Quick ping — is MikoPBX reachable? */
    public function ping(): bool
    {
        try {
            $this->api->getVersion();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Get system resource usage */
    public function systemInfo(): array
    {
        try {
            return $this->api->getSystemStatus();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
