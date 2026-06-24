<?php

namespace BitDreamIT\MikoPBX\Events;

use BitDreamIT\MikoPBX\Models\CallLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEndedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CallLog $callLog) {}

    public function broadcastOn(): Channel
    {
        return new Channel('mikopbx.calls');
    }

    public function broadcastAs(): string { return 'ended'; }

    public function broadcastWith(): array
    {
        return [
            'id'       => $this->callLog->id,
            'caller'   => $this->callLog->caller,
            'extension'=> $this->callLog->extension,
            'status'   => $this->callLog->status,
            'duration' => $this->callLog->duration_formatted,
            'cause'    => $this->callLog->cause,
        ];
    }
}
