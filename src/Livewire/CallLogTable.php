<?php

namespace BitDreamIT\MikoPBX\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use BitDreamIT\MikoPBX\Models\CallLog;

class CallLogTable extends Component
{
    use WithPagination;

    public string $search    = '';
    public string $status    = '';
    public string $direction = '';
    public string $date      = '';
    public string $extension = '';

    protected $queryString = ['search','status','direction','date','extension'];

    protected $listeners = [
        'echo:mikopbx.calls,incoming' => '$refresh',
        'echo:mikopbx.calls,ended'    => '$refresh',
    ];

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedStatus(): void    { $this->resetPage(); }
    public function updatedDirection(): void { $this->resetPage(); }
    public function updatedDate(): void      { $this->resetPage(); }

    public function render(): \Illuminate\View\View
    {
        $query = CallLog::query()->latest('started_at');

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($q) => $q->where('caller','like',"%{$s}%")->orWhere('callee','like',"%{$s}%"));
        }
        if ($this->status)    $query->where('status', $this->status);
        if ($this->direction) $query->where('direction', $this->direction);
        if ($this->date)      $query->whereDate('started_at', $this->date);
        if ($this->extension) $query->where('extension', $this->extension);

        $calls = $query->paginate(20);

        return view('mikopbx::livewire.call-log-table', compact('calls'));
    }
}
