<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extension extends Model
{
    protected $table = 'mikopbx_extensions';

    protected $fillable = [
        'number', 'name', 'email',
        'sip_peer', 'department', 'online',
        'status', 'last_seen_at',
    ];

    protected $casts = [
        'online'       => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'extension', 'number');
    }

    public function scopeOnline(Builder $q): Builder  { return $q->where('online', true); }
    public function scopeOffline(Builder $q): Builder { return $q->where('online', false); }
}
