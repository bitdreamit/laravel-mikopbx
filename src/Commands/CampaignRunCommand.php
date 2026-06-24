<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\CampaignService;
use BitDreamIT\MikoPBX\Models\Campaign;

class CampaignRunCommand extends Command
{
    protected $signature   = 'mikopbx:campaign-run {--sync : Sync progress of running campaigns}';
    protected $description = 'Start scheduled campaigns or sync progress of running ones';

    public function handle(CampaignService $svc): int
    {
        if ($this->option('sync')) {
            Campaign::where('status', 'running')->each(function ($c) use ($svc) {
                $status = $svc->syncProgress($c);
                $this->line("📊 Campaign [{$c->name}] progress: {$c->fresh()->progress}%");
            });
            return 0;
        }

        // Start campaigns scheduled for now
        $due = Campaign::where('status', 'draft')
            ->where('scheduled_at', '<=', now())
            ->whereNotNull('scheduled_at')
            ->get();

        foreach ($due as $campaign) {
            $this->info("▶ Starting campaign: {$campaign->name}");
            try {
                $svc->start($campaign);
                $this->info("  ✅ Started (MikoPBX task #{$campaign->fresh()->mikopbx_task_id})");
            } catch (\Throwable $e) {
                $this->error("  ❌ Failed: {$e->getMessage()}");
            }
        }

        if ($due->isEmpty()) {
            $this->info('No scheduled campaigns due.');
        }

        return 0;
    }
}
