<?php

namespace BitDreamIT\MikoPBX\Events;

use BitDreamIT\MikoPBX\Models\Extension;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Extension $agent,
        public string $status
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('mikopbx.agents');
    }

    public function broadcastAs(): string { return 'status'; }

    public function broadcastWith(): array
    {
        return [
            'extension' => $this->agent->extension,
            'name'      => $this->agent->name,
            'status'    => $this->status,
            'color'     => $this->agent->status_color,
        ];
    }
}
