<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Models\Callback;
use BitDreamIT\MikoPBX\Services\CallbackService;

class PendingCallbacks extends Component
{
    public int $pollInterval = 15;

    public function attempt(int $id): void
    {
        $cb  = Callback::findOrFail($id);
        $ext = auth()->user()?->extension ?? '101';
        $ok  = app(CallbackService::class)->attempt($cb, $ext);
        $this->dispatch('toast', ['type' => $ok ? 'success' : 'error', 'msg' => $ok ? 'Calling...' : 'Call failed.']);
    }

    public function cancel(int $id): void
    {
        Callback::findOrFail($id)->update(['status' => 'cancelled']);
        $this->dispatch('toast', ['type' => 'info', 'msg' => 'Callback cancelled.']);
    }

    public function render(): \Illuminate\View\View
    {
        $callbacks = Callback::where('status','pending')
            ->orderBy('priority','desc')
            ->orderBy('scheduled_at')
            ->paginate(10);

        return view('mikopbx::livewire.pending-callbacks', compact('callbacks'));
    }
}
