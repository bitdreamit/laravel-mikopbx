<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use BitDreamIT\MikoPBX\Models\Blacklist;
use BitDreamIT\MikoPBX\Services\BlacklistService;

class BlacklistManager extends Component
{
    public string $number    = '';
    public string $reason    = '';
    public string $direction = 'both';
    public string $search    = '';

    public function add(): void
    {
        $this->validate(['number' => 'required|string|max:30']);
        app(BlacklistService::class)->add($this->number, $this->reason, $this->direction);
        $this->number = $this->reason = '';
        $this->dispatch('toast', ['type' => 'success', 'msg' => 'Number added to blacklist.']);
    }

    public function remove(string $number): void
    {
        app(BlacklistService::class)->remove($number);
        $this->dispatch('toast', ['type' => 'info', 'msg' => "Removed: {$number}"]);
    }

    public function render(): \Illuminate\View\View
    {
        $list = Blacklist::query()
            ->when($this->search, fn($q) => $q->where('number','like',"%{$this->search}%"))
            ->latest()->paginate(20);

        return view('mikopbx::livewire.blacklist-manager', compact('list'));
    }
}
