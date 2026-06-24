<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\AgentService;
use BitDreamIT\MikoPBX\Models\Extension;

class AgentStatusGrid extends Component
{
    public array  $agents       = [];
    public int    $pollInterval = 10;

    protected $listeners = [
        'echo:mikopbx.agents,status' => 'onAgentStatus',
    ];

    public function mount(): void { $this->load(); }

    public function load(): void
    {
        $this->agents = app(AgentService::class)->all()
            ->map(fn($a) => [
                'extension' => $a->extension,
                'name'      => $a->name,
                'status'    => $a->status,
                'dot'       => $a->status_dot,
                'email'     => $a->email,
            ])->toArray();
    }

    public function onAgentStatus(array $data): void { $this->load(); }

    public function call(string $extension): void
    {
        $this->dispatch('click-to-call', ['to' => $extension]);
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.agent-status-grid');
    }
}
