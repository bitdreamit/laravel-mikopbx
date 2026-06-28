<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\AgentService;

class AgentStatusGrid extends Component
{
    public array $agents       = [];
    public int   $pollInterval = 10;

    protected $listeners = [
        'echo:mikopbx.agents,status' => 'onAgentStatus',
    ];

    public function mount(): void { $this->load(); }

    public function load(): void
    {
        $this->agents = app(AgentService::class)->all()
            ->map(fn($a) => [
                'extension' => $a->extension,
                /*
                 * MikoPBX API sometimes returns HTML markup in extension names
                 * (Semantic UI icon tags like <i class="icon">).
                 * strip_tags() removes them so we only display plain text.
                 */
                'name'      => strip_tags($a->name ?? $a->extension),
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
