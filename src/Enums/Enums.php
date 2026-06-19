<?php

namespace BitDreamIT\MikoPBX\Enums;

enum CallDirection: string
{
    case Inbound  = 'inbound';
    case Outbound = 'outbound';
    case Internal = 'internal';
}

enum HangupCause: string
{
    case Normal           = 'NORMAL_CLEARING';
    case Busy             = 'USER_BUSY';
    case NoAnswer         = 'NO_ANSWER';
    case NoUserResponse   = 'NO_USER_RESPONSE';
    case Unavailable      = 'CHANUNAVAIL';
    case Congestion       = 'CONGESTION';
    case Failed           = 'CALL_REJECTED';
    case Unknown          = 'UNKNOWN';

    public function isMissed(): bool
    {
        return in_array($this, [self::NoAnswer, self::NoUserResponse, self::Unavailable]);
    }

    public function isBusy(): bool
    {
        return $this === self::Busy;
    }

    public function requiresCallback(): bool
    {
        return $this->isMissed() || $this->isBusy();
    }
}

enum CampaignStatus: string
{
    case Created  = 'created';
    case Running  = 'running';
    case Paused   = 'paused';
    case Stopped  = 'stopped';
    case Finished = 'finished';
    case Failed   = 'failed';
}

enum AgentStatus: string
{
    case Online      = 'REGISTERED';
    case Offline     = 'UNREACHABLE';
    case Busy        = 'INUSE';
    case Unavailable = 'UNAVAILABLE';
    case Ringing     = 'RINGING';

    public function isAvailable(): bool
    {
        return $this === self::Online;
    }

    public function label(): string
    {
        return match($this) {
            self::Online      => '🟢 Online',
            self::Offline     => '🔴 Offline',
            self::Busy        => '🟡 In Call',
            self::Unavailable => '⚫ Unavailable',
            self::Ringing     => '🔵 Ringing',
        };
    }
}
