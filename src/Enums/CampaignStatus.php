<?php

namespace BitDreamIT\MikoPBX\Enums;

enum CampaignStatus: string
{
    case Draft     = 'draft';
    case Running   = 'running';
    case Paused    = 'paused';
    case Completed = 'completed';
    case Failed    = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Draft',
            self::Running   => 'Running',
            self::Paused    => 'Paused',
            self::Completed => 'Completed',
            self::Failed    => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Draft     => 'bg-gray-100 text-gray-700',
            self::Running   => 'bg-green-100 text-green-800',
            self::Paused    => 'bg-yellow-100 text-yellow-800',
            self::Completed => 'bg-blue-100 text-blue-800',
            self::Failed    => 'bg-red-100 text-red-800',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Running;
    }

    public function canStart(): bool
    {
        return in_array($this, [self::Draft, self::Paused]);
    }
}
