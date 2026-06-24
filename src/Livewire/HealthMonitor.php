<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\HealthCheckService;

class HealthMonitor extends Component
{
    public int   $pollInterval = 60;
    public array $result       = [];
    public bool  $checking     = false;

    public function mount(): void { $this->load(); }

    public function load(): void
    {
        $latest = app(HealthCheckService::class)->latest();
        $this->result = $latest?->details ?? ['status' => 'unknown'];
    }

    public function runCheck(): void
    {
        $this->checking = true;
        $this->result   = app(HealthCheckService::class)->check();
        $this->checking = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.health-monitor', ['result' => $this->result]);
    }
}
