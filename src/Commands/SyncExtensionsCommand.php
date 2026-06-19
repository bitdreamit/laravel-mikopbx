<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Models\Extension;

class SyncExtensionsCommand extends Command
{
    protected $signature   = 'mikopbx:sync-extensions';
    protected $description = 'Sync extension statuses from MikoPBX to local database';

    public function handle(): int
    {
        $this->info('Syncing extensions from MikoPBX...');
        try {
            $data = MikoPBX::call()->getExtensionStatuses()['data'] ?? [];
            $bar  = $this->output->createProgressBar(count($data));
            $bar->start();
            foreach ($data as $ext) {
                Extension::updateOrCreate(
                    ['number' => $ext['number'] ?? ''],
                    ['online' => in_array($ext['status'] ?? '', ['REGISTERED', 'OK']), 'status' => $ext['status'] ?? 'UNREACHABLE', 'last_seen_at' => now()]
                );
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info('Synced ' . count($data) . ' extensions.');
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
