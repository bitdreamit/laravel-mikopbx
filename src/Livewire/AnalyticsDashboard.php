<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\AnalyticsService;

class AnalyticsDashboard extends Component
{
    public string $from       = '';
    public string $to         = '';
    public array  $summary    = [];
    public array  $daily      = [];
    public array  $peakHours  = [];
    public array  $agents     = [];
    public array  $byStatus   = [];

    public function mount(): void
    {
        $this->from = now()->subDays(30)->format('Y-m-d');
        $this->to   = now()->format('Y-m-d');
        $this->load();
    }

    public function updatedFrom(): void { $this->load(); }
    public function updatedTo(): void   { $this->load(); }

    public function load(): void
    {
        $svc = app(AnalyticsService::class);
        $this->summary   = $svc->summary($this->from, $this->to);
        $this->daily     = $svc->dailyTrend($this->from, $this->to);
        $this->peakHours = $svc->peakHours($this->from, $this->to);
        $this->agents    = $svc->agentPerformance($this->from, $this->to);
        $this->byStatus  = $svc->callsByStatus($this->from, $this->to);
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.analytics-dashboard', [
            'summary'   => $this->summary,
            'daily'     => $this->daily,
            'peakHours' => $this->peakHours,
            'agents'    => $this->agents,
            'byStatus'  => $this->byStatus,
            'from'      => $this->from,
            'to'        => $this->to,
        ]);
    }
}
