<?php

namespace BitDreamIT\MikoPBX\Enums;

enum AgentStatus: string
{
    case Online  = 'online';
    case Offline = 'offline';
    case Busy    = 'busy';
    case DND     = 'dnd';
    case Away    = 'away';

    public function dot(): string
    {
        return match($this) {
            self::Online  => 'bg-green-400',
            self::Busy    => 'bg-orange-400',
            self::DND     => 'bg-red-400',
            self::Away    => 'bg-yellow-400',
            self::Offline => 'bg-gray-300',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::Online;
    }
}
