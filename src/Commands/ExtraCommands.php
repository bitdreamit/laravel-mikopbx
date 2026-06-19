<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Models\Extension;

// ── Sync Extensions Command ───────────────────────────────────────

class SyncExtensionsCommand extends Command
{
    protected $signature   = 'mikopbx:sync-extensions';
    protected $description = 'Sync extension statuses from MikoPBX to local database';

    public function handle(): int
    {
        $this->info('🔄 Syncing extensions from MikoPBX...');

        try {
            $statuses = MikoPBX::call()->getExtensionStatuses();
            $data     = $statuses['data'] ?? [];

            $bar = $this->output->createProgressBar(count($data));
            $bar->start();

            foreach ($data as $ext) {
                Extension::updateOrCreate(
                    ['number' => $ext['number'] ?? ''],
                    [
                        'online'       => in_array($ext['status'] ?? '', ['REGISTERED', 'OK']),
                        'status'       => $ext['status'] ?? 'UNREACHABLE',
                        'last_seen_at' => now(),
                    ]
                );
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Synced " . count($data) . " extensions.");
        } catch (\Throwable $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

// ── Health Check Command ──────────────────────────────────────────

class HealthCheckCommand extends Command
{
    protected $signature   = 'mikopbx:health';
    protected $description = 'Run a full health check on MikoPBX system';

    public function handle(): int
    {
        $this->info('🏥 Running MikoPBX health check...');
        $this->newLine();

        $health = MikoPBX::health()->check();

        $color = match($health['overall']) {
            'healthy'  => 'green',
            'degraded' => 'yellow',
            default    => 'red',
        };

        $this->line("Overall status: <fg={$color};options=bold>{$health['overall']}</>");
        $this->newLine();

        $rows = [];
        foreach ($health['checks'] as $name => $result) {
            $icon   = $result['status'] === 'ok' ? '✅' : '❌';
            $detail = implode(', ', array_filter(array_map(
                fn($k, $v) => $k !== 'status' ? "$k: $v" : null,
                array_keys($result), $result
            )));
            $rows[] = [$icon . ' ' . str_replace('_', ' ', ucfirst($name)), $result['status'], $detail];
        }

        $this->table(['Check', 'Status', 'Detail'], $rows);

        return $health['overall'] === 'unhealthy' ? self::FAILURE : self::SUCCESS;
    }
}

// ── CDR Sync Command ──────────────────────────────────────────────

class CdrSyncCommand extends Command
{
    protected $signature   = 'mikopbx:cdr-sync {--from= : Date from (Y-m-d)} {--to= : Date to (Y-m-d)}';
    protected $description = 'Sync CDR (call detail records) from MikoPBX to local database';

    public function handle(): int
    {
        $from = $this->option('from') ?? today()->toDateString();
        $to   = $this->option('to')   ?? today()->toDateString();

        $this->info("📊 Syncing CDR from $from to $to...");

        try {
            $records = MikoPBX::call()->getRecordings($from, $to);
            $data    = $records['data'] ?? [];

            $this->info('Found ' . count($data) . ' records.');

            $bar = $this->output->createProgressBar(count($data));
            $bar->start();

            foreach ($data as $record) {
                \BitDreamIT\MikoPBX\Models\CdrSync::updateOrCreate(
                    ['uniqueid' => $record['uniqueid'] ?? uniqid()],
                    [
                        'src'           => $record['src']      ?? '',
                        'dst'           => $record['dst']      ?? '',
                        'duration'      => $record['duration'] ?? 0,
                        'billsec'       => $record['billsec']  ?? 0,
                        'disposition'   => $record['disposition'] ?? '',
                        'recordingfile' => $record['recordingfile'] ?? '',
                        'calldate'      => $record['calldate'] ?? now(),
                    ]
                );
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('✅ CDR sync complete!');
        } catch (\Throwable $e) {
            $this->error('❌ CDR sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
