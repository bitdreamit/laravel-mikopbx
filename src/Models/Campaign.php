<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'mikopbx_campaigns';

    protected $fillable = [
        'name', 'mikopbx_task_id', 'audio_file',
        'max_channels', 'status', 'total_numbers',
        'dialed_count', 'answered_count',
        'started_at', 'stopped_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function numbers(): HasMany
    {
        return $this->hasMany(CampaignNumber::class);
    }

    public function scopeRunning(Builder $q): Builder { return $q->where('status', 'running'); }
    public function scopeStopped(Builder $q): Builder { return $q->where('status', 'stopped'); }
}
