<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];

    public function getTable(): string
    {
        return config('mikopbx.table_prefix', 'mikopbx_') . 'blacklist';
    }
}
