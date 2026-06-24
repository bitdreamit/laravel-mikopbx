<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Services\RestApiService;

class LiveCallBoard extends Component
{
    public array  $activeCalls   = [];
    public int    $pollInterval  = 5;  // seconds
    public string $selectedCall  = '';
    public string $transferTo    = '';
    public bool   $showTransfer  = false;

    protected $listeners = [
        'echo:mikopbx.calls,incoming' => 'onIncoming',
        'echo:mikopbx.calls,ended'    => 'onEnded',
        'echo:mikopbx.calls,answered' => 'refresh',
    ];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        try {
            $svc = app(RestApiService::class);
            $this->activeCalls = $svc->getActiveCalls()['data'] ?? [];
        } catch (\Throwable) {
            $this->activeCalls = [];
        }
    }

    public function onIncoming(array $data): void
    {
        $this->refresh();
        $this->dispatch('incoming-call-sound');
    }

    public function onEnded(array $data): void
    {
        $this->refresh();
    }

    public function openTransfer(string $channel): void
    {
        $this->selectedCall = $channel;
        $this->showTransfer = true;
    }

    public function doTransfer(): void
    {
        if (! $this->selectedCall || ! $this->transferTo) return;

        try {
            app(RestApiService::class)->transfer($this->selectedCall, $this->transferTo);
            $this->showTransfer = false;
            $this->transferTo   = '';
            $this->dispatch('toast', ['type' => 'success', 'msg' => 'Call transferred.']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => $e->getMessage()]);
        }

        $this->refresh();
    }

    public function hangup(string $channel): void
    {
        try {
            app(RestApiService::class)->hangup($channel);
            $this->dispatch('toast', ['type' => 'success', 'msg' => 'Call ended.']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'msg' => $e->getMessage()]);
        }
        $this->refresh();
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.live-call-board');
    }
}
