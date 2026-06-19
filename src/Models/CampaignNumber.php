<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignNumber extends Model
{
    protected $table = 'mikopbx_campaign_numbers';

    protected $fillable = [
        'campaign_id', 'number', 'status',
        'attempts', 'last_attempt_at', 'result',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
