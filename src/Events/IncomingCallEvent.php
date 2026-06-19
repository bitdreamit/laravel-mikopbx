<?php

namespace BitDreamIT\MikoPBX\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $callerNumber,
        public readonly string  $extension,
        public readonly string  $channel,
        public readonly ?string $callerName = null,
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
        return 'call.incoming';
    }

    public function broadcastWith(): array
    {
        return [
            'caller_number' => $this->callerNumber,
            'caller_name'   => $this->callerName,
            'extension'     => $this->extension,
            'channel'       => $this->channel,
            'timestamp'     => now()->toISOString(),
        ];
    }
}
