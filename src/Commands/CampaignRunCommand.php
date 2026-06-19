<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Models\Campaign;
use BitDreamIT\MikoPBX\Services\CampaignService;

class CampaignRunCommand extends Command
{
    protected $signature   = 'mikopbx:campaign {action : start|stop|status} {id : Campaign ID}';
    protected $description = 'Manage MikoPBX campaigns from CLI';

    public function handle(CampaignService $campaign): int
    {
        $model = Campaign::find($this->argument('id'));
        if (!$model) { $this->error('Campaign #' . $this->argument('id') . ' not found'); return self::FAILURE; }
        match ($this->argument('action')) {
            'start'  => [$campaign->start($model),  $this->info("Campaign [{$model->name}] started!")],
            'stop'   => [$campaign->stop($model),   $this->info("Campaign [{$model->name}] stopped!")],
            'status' => $this->showStatus($campaign, $model),
            default  => $this->error("Unknown action: " . $this->argument('action')),
        };
        return self::SUCCESS;
    }

    private function showStatus(CampaignService $svc, Campaign $c): void
    {
        $svc->status($c);
        $this->table(['Field','Value'], [
            ['Name', $c->name], ['Status', $c->status],
            ['Total', $c->total_numbers], ['Dialed', $c->dialed_count],
            ['Answered', $c->answered_count], ['Started', $c->started_at],
        ]);
    }
}
