<?php

namespace BitDreamIT\MikoPBX\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// ── Incoming Call ─────────────────────────────────────────────────

class IncomingCallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $callerNumber,
        public readonly string  $extension,
        public readonly string  $channel,
        public readonly ?string $callerName  = null,
        public readonly ?string $queueName   = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('mikopbx.calls'),
            new Channel('mikopbx.extension.' . $this->extension),
        ];
    }

    public function broadcastAs(): string { return 'call.incoming'; }

    public function broadcastWith(): array
    {
        return [
            'caller_number' => $this->callerNumber,
            'caller_name'   => $this->callerName,
            'extension'     => $this->extension,
            'channel'       => $this->channel,
            'queue'         => $this->queueName,
            'timestamp'     => now()->toISOString(),
        ];
    }
}

// ── Call Answered ─────────────────────────────────────────────────

class CallAnsweredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $extension,
        public readonly int    $waitSeconds = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('mikopbx.calls'),
            new Channel('mikopbx.extension.' . $this->extension),
        ];
    }

    public function broadcastAs(): string { return 'call.answered'; }

    public function broadcastWith(): array
    {
        return [
            'channel'      => $this->channel,
            'extension'    => $this->extension,
            'wait_seconds' => $this->waitSeconds,
            'timestamp'    => now()->toISOString(),
        ];
    }
}

// ── Call Ended ────────────────────────────────────────────────────

class CallEndedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $cause,
        public readonly int    $duration,
        public readonly string $extension  = '',
        public readonly ?string $recording = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('mikopbx.calls'),
            new Channel('mikopbx.extension.' . $this->extension),
        ];
    }

    public function broadcastAs(): string { return 'call.ended'; }

    public function broadcastWith(): array
    {
        return [
            'channel'   => $this->channel,
            'cause'     => $this->cause,
            'duration'  => $this->duration,
            'extension' => $this->extension,
            'recording' => $this->recording,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function isMissed(): bool
    {
        return in_array($this->cause, ['NO_ANSWER', 'NO_USER_RESPONSE', 'CHANUNAVAIL']);
    }
}

// ── Call Missed ───────────────────────────────────────────────────

class CallMissedEvent implements ShouldBroadcast
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
            new Channel('mikopbx.calls'),
            new Channel('mikopbx.extension.' . $this->extension),
            new Channel('mikopbx.missed'),
        ];
    }

    public function broadcastAs(): string { return 'call.missed'; }

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

// ── Call Transferred ──────────────────────────────────────────────

class CallTransferredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $fromExtension,
        public readonly string $toExtension,
        public readonly string $type = 'blind', // blind|attended
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('mikopbx.calls')];
    }

    public function broadcastAs(): string { return 'call.transferred'; }

    public function broadcastWith(): array
    {
        return [
            'channel'        => $this->channel,
            'from_extension' => $this->fromExtension,
            'to_extension'   => $this->toExtension,
            'type'           => $this->type,
            'timestamp'      => now()->toISOString(),
        ];
    }
}

// ── Agent Status Changed ──────────────────────────────────────────

class AgentStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $extension,
        public readonly string $status,
        public readonly bool   $online,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('mikopbx.agents'),
            new Channel('mikopbx.extension.' . $this->extension),
        ];
    }

    public function broadcastAs(): string { return 'agent.status'; }

    public function broadcastWith(): array
    {
        return [
            'extension' => $this->extension,
            'status'    => $this->status,
            'online'    => $this->online,
            'timestamp' => now()->toISOString(),
        ];
    }
}

// ── Queue Events ──────────────────────────────────────────────────

class CallerJoinedQueueEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $callerNumber,
        public readonly string $queue,
        public readonly int    $position,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('mikopbx.queue.' . $this->queue)];
    }

    public function broadcastAs(): string { return 'queue.joined'; }

    public function broadcastWith(): array
    {
        return [
            'caller'    => $this->callerNumber,
            'queue'     => $this->queue,
            'position'  => $this->position,
            'timestamp' => now()->toISOString(),
        ];
    }
}

class CallerLeftQueueEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $callerNumber,
        public readonly string $queue,
        public readonly string $reason = 'answered', // answered|abandoned|timeout
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('mikopbx.queue.' . $this->queue)];
    }

    public function broadcastAs(): string { return 'queue.left'; }

    public function broadcastWith(): array
    {
        return [
            'caller'    => $this->callerNumber,
            'queue'     => $this->queue,
            'reason'    => $this->reason,
            'timestamp' => now()->toISOString(),
        ];
    }
}

// ── Campaign Events ───────────────────────────────────────────────

class CampaignStartedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $campaignId,
        public readonly string $campaignName,
    ) {}
}

class CampaignCompletedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int    $campaignId,
        public readonly string $campaignName,
        public readonly int    $total,
        public readonly int    $answered,
        public readonly int    $missed,
    ) {}
}

// ── Conference Events ─────────────────────────────────────────────

class ConferenceParticipantJoinedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $bridgeId,
        public readonly string $callerNumber,
        public readonly string $channel,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('mikopbx.conference.' . $this->bridgeId)];
    }

    public function broadcastAs(): string { return 'conference.joined'; }

    public function broadcastWith(): array
    {
        return ['bridge_id' => $this->bridgeId, 'caller' => $this->callerNumber, 'channel' => $this->channel, 'timestamp' => now()->toISOString()];
    }
}

// ── Voicemail Event ───────────────────────────────────────────────

class NewVoicemailEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $mailbox,
        public readonly string  $callerNumber,
        public readonly int     $duration      = 0,
        public readonly ?string $recordingFile = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('mikopbx.voicemail.' . $this->mailbox)];
    }

    public function broadcastAs(): string { return 'voicemail.new'; }

    public function broadcastWith(): array
    {
        return [
            'mailbox'   => $this->mailbox,
            'caller'    => $this->callerNumber,
            'duration'  => $this->duration,
            'recording' => $this->recordingFile,
            'timestamp' => now()->toISOString(),
        ];
    }
}

// ── Call Recorded ─────────────────────────────────────────────────

class CallRecordedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $extension,
        public readonly string $filename,
        public readonly int    $duration,
    ) {}
}
