<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\RestApiService;
use BitDreamIT\MikoPBX\Models\CallLog;

class CdrSyncCommand extends Command
{
    protected $signature   = 'mikopbx:cdr-sync {--days=1 : How many days back to sync}';
    protected $description = 'Sync CDR (call logs) from MikoPBX into local database';

    public function handle(RestApiService $api): int
    {
        $days = (int) $this->option('days');
        $from = now()->subDays($days)->format('Y-m-d 00:00:00');
        $to   = now()->format('Y-m-d 23:59:59');

        $this->info("Syncing CDR from {$from} to {$to}...");

        try {
            $records = $api->getCDR($from, $to)['data'] ?? [];
            $count = 0;

            foreach ($records as $rec) {
                CallLog::updateOrCreate(
                    ['uniqueid' => $rec['uniqueid'] ?? $rec['id'] ?? null],
                    [
                        'caller'       => $rec['src']        ?? $rec['caller'] ?? '',
                        'callee'       => $rec['dst']        ?? $rec['callee'] ?? '',
                        'extension'    => $rec['dstchannel'] ?? $rec['extension'] ?? '',
                        'direction'    => $rec['direction']  ?? 'inbound',
                        'status'       => $rec['disposition'] ?? $rec['status'] ?? 'ended',
                        'duration'     => $rec['duration']   ?? 0,
                        'billsec'      => $rec['billsec']    ?? 0,
                        'started_at'   => $rec['calldate']   ?? $rec['started_at'] ?? null,
                    ]
                );
                $count++;
            }

            $this->info("✅ Synced {$count} records.");
        } catch (\Throwable $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
