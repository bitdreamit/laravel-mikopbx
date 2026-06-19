<?php

namespace BitDreamIT\MikoPBX\Enums;

enum CallStatus: string
{
    case Ringing  = 'ringing';
    case Answered = 'answered';
    case Ended    = 'ended';
    case Missed   = 'missed';
    case Busy     = 'busy';
    case Failed   = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Ringing  => '📞 Ringing',
            self::Answered => '✅ Answered',
            self::Ended    => '📵 Ended',
            self::Missed   => '❌ Missed',
            self::Busy     => '🔴 Busy',
            self::Failed   => '⚠️ Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Ringing  => 'blue',
            self::Answered => 'green',
            self::Ended    => 'gray',
            self::Missed   => 'red',
            self::Busy     => 'orange',
            self::Failed   => 'yellow',
        };
    }
}
