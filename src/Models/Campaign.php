<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $guarded = [];
    protected $casts = [
        'scheduled_at'  => 'datetime',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'ivr_script'    => 'array',
        'meta'          => 'array',
    ];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'campaigns';
    }

    public function numbers(): HasMany  { return $this->hasMany(CampaignNumber::class); }
    public function callLogs(): HasMany { return $this->hasMany(CallLog::class); }

    public function getProgressAttribute(): float
    {
        if (! $this->total_numbers) return 0;
        return round($this->dialed / $this->total_numbers * 100, 1);
    }

    public function isRunning(): bool { return $this->status === 'running'; }
    public function isPaused(): bool  { return $this->status === 'paused'; }
    public function isDone(): bool    { return in_array($this->status, ['completed', 'failed']); }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'running'   => 'bg-green-100 text-green-800',
            'paused'    => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-blue-100 text-blue-800',
            'failed'    => 'bg-red-100 text-red-800',
            default     => 'bg-gray-100 text-gray-800',
        };
    }
}
