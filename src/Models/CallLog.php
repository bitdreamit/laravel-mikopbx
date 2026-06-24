<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $guarded = [];
    protected $casts = [
        'started_at'  => 'datetime',
        'answered_at' => 'datetime',
        'ended_at'    => 'datetime',
        'meta'        => 'array',
    ];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'call_logs';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function getDurationFormattedAttribute(): string
    {
        $s = $this->billsec ?? $this->duration ?? 0;
        return sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }

    public function scopeInbound($q)  { return $q->where('direction', 'inbound'); }
    public function scopeOutbound($q) { return $q->where('direction', 'outbound'); }
    public function scopeAnswered($q) { return $q->where('status', 'answered'); }
    public function scopeMissed($q)   { return $q->where('status', 'missed'); }
    public function scopeToday($q)    { return $q->whereDate('started_at', today()); }
}
