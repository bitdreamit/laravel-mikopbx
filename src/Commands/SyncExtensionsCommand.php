<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\AgentService;

class SyncExtensionsCommand extends Command
{
    protected $signature   = 'mikopbx:sync-extensions';
    protected $description = 'Pull extensions from MikoPBX and upsert local database';

    public function handle(AgentService $agents): int
    {
        $this->info('Syncing extensions from MikoPBX...');
        try {
            $count = $agents->sync();
            $this->info("✅ Synced {$count} extensions.");
        } catch (\Throwable $e) {
            $this->error("❌ {$e->getMessage()}");
            return 1;
        }
        return 0;
    }
}
