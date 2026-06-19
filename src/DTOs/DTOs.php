<?php

namespace BitDreamIT\MikoPBX\DTOs;

use BitDreamIT\MikoPBX\Enums\CallStatus;
use BitDreamIT\MikoPBX\Enums\CallDirection;
use Illuminate\Support\Carbon;

/**
 * CallDTO — Immutable type-safe call data transfer object
 */
final class CallDTO
{
    public function __construct(
        public readonly string       $channel,
        public readonly string       $caller,
        public readonly string       $extension,
        public readonly CallDirection $direction,
        public readonly CallStatus   $status,
        public readonly int          $duration    = 0,
        public readonly ?string      $callerName  = null,
        public readonly ?string      $cause       = null,
        public readonly ?string      $recording   = null,
        public readonly ?Carbon      $startedAt   = null,
        public readonly ?Carbon      $answeredAt  = null,
        public readonly ?Carbon      $endedAt     = null,
    ) {}

    public static function fromAMIEvent(array $event): static
    {
        return new static(
            channel:   $event['Channel']      ?? '',
            caller:    $event['CallerIDNum']   ?? '',
            extension: $event['Exten']         ?? '',
            direction: CallDirection::Inbound,
            status:    CallStatus::Ringing,
            startedAt: now(),
        );
    }

    public static function fromCallLog(\BitDreamIT\MikoPBX\Models\CallLog $log): static
    {
        return new static(
            channel:    $log->channel    ?? '',
            caller:     $log->caller     ?? '',
            extension:  $log->extension  ?? '',
            direction:  CallDirection::from($log->direction ?? 'inbound'),
            status:     CallStatus::from($log->status      ?? 'ended'),
            duration:   $log->duration   ?? 0,
            callerName: $log->caller_name,
            cause:      $log->cause,
            recording:  $log->recording_file,
            startedAt:  $log->started_at,
            answeredAt: $log->answered_at,
            endedAt:    $log->ended_at,
        );
    }

    public function toArray(): array
    {
        return [
            'channel'    => $this->channel,
            'caller'     => $this->caller,
            'caller_name'=> $this->callerName,
            'extension'  => $this->extension,
            'direction'  => $this->direction->value,
            'status'     => $this->status->value,
            'duration'   => $this->duration,
            'cause'      => $this->cause,
            'recording'  => $this->recording,
            'started_at' => $this->startedAt?->toISOString(),
            'answered_at'=> $this->answeredAt?->toISOString(),
            'ended_at'   => $this->endedAt?->toISOString(),
        ];
    }

    public function isMissed(): bool
    {
        return $this->status === CallStatus::Missed || ($this->status === CallStatus::Ended && $this->answeredAt === null);
    }

    public function waitTime(): int
    {
        if (!$this->startedAt || !$this->answeredAt) return 0;
        return $this->startedAt->diffInSeconds($this->answeredAt);
    }
}

/**
 * OriginateDTO — Parameters for making an outbound call
 */
final class OriginateDTO
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $context     = 'from-internal',
        public readonly string $callerId    = '',
        public readonly int    $timeout     = 30000,
        public readonly array  $variables   = [],
    ) {}

    public static function make(string $from, string $to): static
    {
        return new static(from: $from, to: $to);
    }

    public function withCallerId(string $callerId): static
    {
        return new static($this->from, $this->to, $this->context, $callerId, $this->timeout, $this->variables);
    }

    public function withTimeout(int $ms): static
    {
        return new static($this->from, $this->to, $this->context, $this->callerId, $ms, $this->variables);
    }

    public function withVariable(string $key, string $value): static
    {
        $vars = array_merge($this->variables, [$key => $value]);
        return new static($this->from, $this->to, $this->context, $this->callerId, $this->timeout, $vars);
    }
}

/**
 * CampaignDTO — Campaign creation parameters
 */
final class CampaignDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly array   $numbers,
        public readonly string  $audioFile,
        public readonly int     $maxChannels  = 5,
        public readonly array   $ivrOptions   = [],
        public readonly string  $type         = 'broadcast',
        public readonly ?string $scheduledAt  = null,
    ) {}

    public static function broadcast(string $name, array $numbers, string $audioFile): static
    {
        return new static(name: $name, numbers: $numbers, audioFile: $audioFile);
    }

    public static function withIVR(string $name, array $numbers, string $audioFile, array $keypresses): static
    {
        return new static(
            name: $name, numbers: $numbers, audioFile: $audioFile,
            type: 'ivr_survey', ivrOptions: $keypresses
        );
    }
}

/**
 * AgentDTO — Agent/extension data
 */
final class AgentDTO
{
    public function __construct(
        public readonly string  $extension,
        public readonly string  $name        = '',
        public readonly string  $status      = 'UNREACHABLE',
        public readonly bool    $online      = false,
        public readonly string  $department  = '',
        public readonly ?string $currentCall = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            extension:   $data['number']     ?? $data['extension'] ?? '',
            name:        $data['name']        ?? '',
            status:      $data['status']      ?? 'UNREACHABLE',
            online:      in_array($data['status'] ?? '', ['REGISTERED', 'OK']),
            department:  $data['department']  ?? '',
            currentCall: $data['channel']     ?? null,
        );
    }

    public function isAvailable(): bool { return $this->online && $this->currentCall === null; }
    public function isInCall(): bool    { return $this->currentCall !== null; }

    public function toArray(): array
    {
        return [
            'extension'    => $this->extension,
            'name'         => $this->name,
            'status'       => $this->status,
            'online'       => $this->online,
            'department'   => $this->department,
            'current_call' => $this->currentCall,
            'available'    => $this->isAvailable(),
        ];
    }
}
