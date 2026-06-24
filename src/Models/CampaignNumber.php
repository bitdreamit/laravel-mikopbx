<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignNumber extends Model
{
    protected $guarded = [];
    protected $casts = [
        'last_attempted_at' => 'datetime',
        'next_attempt_at'   => 'datetime',
        'meta'              => 'array',
    ];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'campaign_numbers';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
