<?php

namespace BitDreamIT\MikoPBX\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEndedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $cause,
        public readonly int    $duration,
        public readonly string $extension,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('mikopbx.extension.' . $this->extension),
            new Channel('mikopbx.calls'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'channel'   => $this->channel,
            'cause'     => $this->cause,
            'duration'  => $this->duration,
            'extension' => $this->extension,
            'timestamp' => now()->toISOString(),
        ];
    }
}
