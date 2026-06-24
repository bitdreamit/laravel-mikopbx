<?php

namespace BitDreamIT\MikoPBX\Events;

use BitDreamIT\MikoPBX\Models\CallLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CallLog $callLog) {}

    public function broadcastOn(): Channel
    {
        return new Channel('mikopbx.calls');
    }

    public function broadcastAs(): string { return 'incoming'; }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->callLog->id,
            'caller'    => $this->callLog->caller,
            'extension' => $this->callLog->extension,
            'channel'   => $this->callLog->channel,
            'started_at'=> $this->callLog->started_at?->toIso8601String(),
        ];
    }
}
