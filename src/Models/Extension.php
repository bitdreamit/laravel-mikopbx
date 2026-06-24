<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    protected $guarded = [];
    protected $casts = [
        'last_seen_at' => 'datetime',
        'meta'         => 'array',
    ];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'extensions';
    }

    public function callLogs()  { return $this->hasMany(CallLog::class, 'extension', 'extension'); }
    public function callbacks() { return $this->hasMany(Callback::class, 'assigned_to'); }

    public function getIsOnlineAttribute(): bool
    {
        return in_array($this->status, ['online', 'busy']);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'online'  => 'green',
            'busy'    => 'orange',
            'dnd'     => 'red',
            'away'    => 'yellow',
            default   => 'gray',
        };
    }

    public function getStatusDotAttribute(): string
    {
        return match($this->status) {
            'online'  => 'bg-green-400',
            'busy'    => 'bg-orange-400',
            'dnd'     => 'bg-red-400',
            'away'    => 'bg-yellow-400',
            default   => 'bg-gray-300',
        };
    }
}
