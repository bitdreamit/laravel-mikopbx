<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\HealthCheckService;

class HealthCheckCommand extends Command
{
    protected $signature   = 'mikopbx:health';
    protected $description = 'Run a health check against MikoPBX and log result';

    public function handle(HealthCheckService $health): int
    {
        $this->info('Running MikoPBX health check...');
        $result = $health->check();

        $this->table(
            ['Component', 'Status'],
            [
                ['AMI',         $result['amiOk']  ? '✅ Connected' : '❌ Down'],
                ['ARI',         $result['ariOk']  ? '✅ Connected' : '❌ Down'],
                ['SIP Trunk',   $result['sipOk']  ? '✅ Registered' : '⚠️ Not registered'],
                ['Active Calls', $result['calls']],
            ]
        );

        $statusColor = match($result['status']) {
            'healthy'  => 'info',
            'degraded' => 'warn',
            default    => 'error',
        };
        $this->$statusColor("Overall: {$result['status']}");

        return $result['status'] === 'critical' ? 1 : 0;
    }
}
