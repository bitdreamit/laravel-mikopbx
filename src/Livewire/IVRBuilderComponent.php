<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\IVRService;

class IVRBuilderComponent extends Component
{
    public string $name     = '';
    public string $greeting = '';
    public int    $timeout  = 5;
    public int    $retries  = 3;
    public array  $nodes    = [];

    public function mount(): void
    {
        // Default starter node
        $this->nodes = [
            [
                'id'     => 'start',
                'type'   => 'greeting',
                'label'  => 'Welcome Greeting',
                'audio'  => '',
                'keys'   => [
                    ['digit' => '1', 'label' => 'Sales',   'action' => 'extension', 'target' => '101'],
                    ['digit' => '2', 'label' => 'Support', 'action' => 'extension', 'target' => '102'],
                    ['digit' => '0', 'label' => 'Operator','action' => 'extension', 'target' => '100'],
                ],
            ],
        ];
    }

    public function addKey(int $nodeIndex): void
    {
        $this->nodes[$nodeIndex]['keys'][] = [
            'digit'  => '',
            'label'  => '',
            'action' => 'extension',
            'target' => '',
        ];
    }

    public function removeKey(int $nodeIndex, int $keyIndex): void
    {
        array_splice($this->nodes[$nodeIndex]['keys'], $keyIndex, 1);
    }

    public function addNode(): void
    {
        $this->nodes[] = [
            'id'    => 'node_' . count($this->nodes),
            'type'  => 'menu',
            'label' => 'Sub Menu',
            'audio' => '',
            'keys'  => [],
        ];
    }

    public function removeNode(int $index): void
    {
        array_splice($this->nodes, $index, 1);
    }

    public function save(): void
    {
        $this->validate([
            'name'    => 'required|string|max:100',
            'nodes'   => 'required|array|min:1',
        ]);

        try {
            app(IVRService::class)->save([
                'name'     => $this->name,
                'greeting' => $this->greeting,
                'timeout'  => $this->timeout,
                'retries'  => $this->retries,
                'nodes'    => $this->nodes,
            ]);
            $this->dispatch('toast', ['type' => 'success', 'msg' => "IVR \"{$this->name}\" saved to MikoPBX."]);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => $e->getMessage()]);
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.ivr-builder');
    }
}
