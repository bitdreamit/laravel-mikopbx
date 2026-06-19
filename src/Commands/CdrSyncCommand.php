<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Models\CdrSync;

class CdrSyncCommand extends Command
{
    protected $signature   = 'mikopbx:cdr-sync {--from= : Date from (Y-m-d)} {--to= : Date to (Y-m-d)}';
    protected $description = 'Sync CDR records from MikoPBX to local database';

    public function handle(): int
    {
        $from = $this->option('from') ?? today()->toDateString();
        $to   = $this->option('to')   ?? today()->toDateString();
        $this->info("Syncing CDR from $from to $to...");
        try {
            $data = MikoPBX::call()->getRecordings($from, $to)['data'] ?? [];
            $this->info('Found ' . count($data) . ' records.');
            $bar = $this->output->createProgressBar(count($data));
            $bar->start();
            foreach ($data as $record) {
                \BitDreamIT\MikoPBX\Models\CdrSync::updateOrCreate(
                    ['uniqueid' => $record['uniqueid'] ?? uniqid()],
                    ['src' => $record['src'] ?? '', 'dst' => $record['dst'] ?? '', 'duration' => $record['duration'] ?? 0, 'billsec' => $record['billsec'] ?? 0, 'disposition' => $record['disposition'] ?? '', 'recordingfile' => $record['recordingfile'] ?? '', 'calldate' => $record['calldate'] ?? now()]
                );
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info('CDR sync complete!');
        } catch (\Throwable $e) {
            $this->error('CDR sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
