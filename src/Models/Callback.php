<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;

class Callback extends Model
{
    protected $guarded = [];
    protected $casts = [
        'scheduled_at'  => 'datetime',
        'attempted_at'  => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'callbacks';
    }

    public function assignedAgent() { return $this->belongsTo(Extension::class, 'assigned_to'); }
    public function callLog()       { return $this->belongsTo(CallLog::class); }

    public function getPriorityBadgeAttribute(): string
    {
        return match($this->priority) {
            'urgent' => 'bg-red-100 text-red-800',
            'high'   => 'bg-orange-100 text-orange-800',
            'normal' => 'bg-blue-100 text-blue-800',
            default  => 'bg-gray-100 text-gray-800',
        };
    }
}
