<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;

class HealthLog extends Model
{
    protected $guarded = [];
    protected $casts = [
        'details'    => 'array',
        'checked_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'health_logs';
    }
}
