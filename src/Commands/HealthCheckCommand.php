<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Facades\MikoPBX;

class HealthCheckCommand extends Command
{
    protected $signature   = 'mikopbx:health';
    protected $description = 'Run a full health check on MikoPBX system';

    public function handle(): int
    {
        $this->info('Running MikoPBX health check...');
        $this->newLine();
        $health = MikoPBX::health()->check();
        $this->line('Overall: ' . strtoupper($health['overall']));
        $this->newLine();
        $rows = [];
        foreach ($health['checks'] as $name => $result) {
            $rows[] = [str_replace('_', ' ', ucfirst($name)), $result['status'], implode(', ', array_filter(array_map(fn($k,$v) => $k !== 'status' ? "$k: $v" : null, array_keys($result), $result)))];
        }
        $this->table(['Check', 'Status', 'Detail'], $rows);
        return $health['overall'] === 'unhealthy' ? self::FAILURE : self::SUCCESS;
    }
}
