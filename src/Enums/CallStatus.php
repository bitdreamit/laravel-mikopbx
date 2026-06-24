<?php

namespace BitDreamIT\MikoPBX\Enums;

enum CallStatus: string
{
    case Ringing     = 'ringing';
    case Answered    = 'answered';
    case Missed      = 'missed';
    case Busy        = 'busy';
    case Failed      = 'failed';
    case Voicemail   = 'voicemail';
    case Transferred = 'transferred';
    case Ended       = 'ended';

    public function label(): string
    {
        return match($this) {
            self::Ringing     => 'Ringing',
            self::Answered    => 'Answered',
            self::Missed      => 'Missed',
            self::Busy        => 'Busy',
            self::Failed      => 'Failed',
            self::Voicemail   => 'Voicemail',
            self::Transferred => 'Transferred',
            self::Ended       => 'Ended',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Answered    => 'green',
            self::Ringing     => 'blue',
            self::Missed      => 'red',
            self::Busy        => 'orange',
            self::Failed      => 'red',
            self::Voicemail   => 'purple',
            self::Transferred => 'indigo',
            self::Ended       => 'gray',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Answered    => 'bg-green-100 text-green-800',
            self::Ringing     => 'bg-blue-100 text-blue-800',
            self::Missed      => 'bg-red-100 text-red-800',
            self::Busy        => 'bg-orange-100 text-orange-800',
            self::Failed      => 'bg-red-200 text-red-900',
            self::Voicemail   => 'bg-purple-100 text-purple-800',
            self::Transferred => 'bg-indigo-100 text-indigo-800',
            self::Ended       => 'bg-gray-100 text-gray-700',
        };
    }
}
