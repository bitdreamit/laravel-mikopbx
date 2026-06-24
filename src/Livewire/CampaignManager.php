<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Models\Campaign;
use BitDreamIT\MikoPBX\Services\CampaignService;

class CampaignManager extends Component
{
    public int    $pollInterval = 8;
    public array  $campaigns   = [];

    public function mount(): void { $this->load(); }

    public function load(): void
    {
        $svc = app(CampaignService::class);
        $this->campaigns = Campaign::latest()->limit(20)->get()
            ->map(fn($c) => array_merge($c->toArray(), $svc->getStats($c)))
            ->toArray();
    }

    public function start(int $id): void
    {
        $c = Campaign::findOrFail($id);
        app(CampaignService::class)->start($c);
        $this->load();
        $this->dispatch('toast', ['type' => 'success', 'msg' => "Campaign \"{$c->name}\" started."]);
    }

    public function pause(int $id): void
    {
        $c = Campaign::findOrFail($id);
        app(CampaignService::class)->pause($c);
        $this->load();
        $this->dispatch('toast', ['type' => 'info', 'msg' => "Campaign \"{$c->name}\" paused."]);
    }

    public function stop(int $id): void
    {
        $c = Campaign::findOrFail($id);
        app(CampaignService::class)->stop($c);
        $this->load();
        $this->dispatch('toast', ['type' => 'warning', 'msg' => "Campaign \"{$c->name}\" stopped."]);
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.campaign-manager');
    }
}
