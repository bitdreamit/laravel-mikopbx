<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CallLog extends Model
{
    protected $table = 'mikopbx_call_logs';

    protected $fillable = [
        'caller', 'extension', 'channel',
        'status', 'direction', 'duration',
        'cause', 'recording_file',
        'started_at', 'answered_at', 'ended_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'answered_at' => 'datetime',
        'ended_at'    => 'datetime',
    ];

    // Scopes
    public function scopeInbound(Builder $q): Builder   { return $q->where('direction', 'inbound'); }
    public function scopeOutbound(Builder $q): Builder  { return $q->where('direction', 'outbound'); }
    public function scopeMissed(Builder $q): Builder    { return $q->where('status', 'ended')->whereNull('answered_at'); }
    public function scopeAnswered(Builder $q): Builder  { return $q->where('status', 'answered'); }
    public function scopeForExtension(Builder $q, string $ext): Builder { return $q->where('extension', $ext); }
    public function scopeToday(Builder $q): Builder     { return $q->whereDate('started_at', today()); }
}
