<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Models\CallLog;

class IncomingCallPopup extends Component
{
    public bool   $visible   = false;
    public string $caller    = '';
    public string $extension = '';
    public int    $callLogId = 0;

    protected $listeners = [
        'echo:mikopbx.calls,incoming' => 'onIncoming',
        'echo:mikopbx.calls,ended'    => 'onEnded',
    ];

    public function onIncoming(array $data): void
    {
        $this->caller    = $data['caller']    ?? '';
        $this->extension = $data['extension'] ?? '';
        $this->callLogId = $data['id']        ?? 0;
        $this->visible   = true;

        $this->dispatch('play-ringtone');
    }

    public function onEnded(array $data): void
    {
        if (isset($data['id']) && $data['id'] === $this->callLogId) {
            $this->dismiss();
        }
    }

    public function answer(): void
    {
        // In a WebRTC setup, the browser SIP stack handles the actual answer.
        // Here we just dismiss and let the JS softphone take over.
        $this->dispatch('answer-call', ['extension' => $this->extension]);
        $this->dismiss();
    }

    public function reject(): void
    {
        $this->dispatch('reject-call', ['callLogId' => $this->callLogId]);
        $this->dismiss();
    }

    public function logCall(): void
    {
        $this->dispatch('open-log-modal', ['callLogId' => $this->callLogId, 'caller' => $this->caller]);
        $this->dismiss();
    }

    public function dismiss(): void
    {
        $this->visible   = false;
        $this->caller    = '';
        $this->extension = '';
        $this->callLogId = 0;
        $this->dispatch('stop-ringtone');
    }

    public function render(): \Illuminate\View\View
    {
        return view('mikopbx::livewire.incoming-call-popup');
    }
}
