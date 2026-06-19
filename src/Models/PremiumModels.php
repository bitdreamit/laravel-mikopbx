<?php

namespace BitDreamIT\MikoPBX\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $table    = 'mikopbx_blacklist';
    protected $fillable = ['number', 'reason', 'added_by', 'expires_at', 'active'];
    protected $casts    = ['active' => 'boolean', 'expires_at' => 'datetime'];

    public function scopeActive($q) { return $q->where('active', true); }
}

class CallbackRequest extends Model
{
    protected $table    = 'mikopbx_callbacks';
    protected $fillable = ['caller_number', 'caller_name', 'extension', 'queue', 'reason', 'status', 'attempts', 'max_attempts', 'scheduled_at', 'completed_at', 'notes'];
    protected $casts    = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];

    public function scopePending($q)    { return $q->where('status', 'pending'); }
    public function scopeCompleted($q)  { return $q->where('status', 'completed'); }
    public function canRetry(): bool    { return $this->attempts < $this->max_attempts; }
}

class CdrSync extends Model
{
    protected $table    = 'mikopbx_cdr_sync';
    protected $fillable = ['uniqueid','src','dst','dcontext','clid','channel','dstchannel','lastapp','lastdata','calldate','duration','billsec','disposition','amaflags','accountcode','userfield','recordingfile'];
    protected $casts    = ['calldate' => 'datetime'];
}

class IVRMenu extends Model
{
    protected $table    = 'mikopbx_ivr_menus';
    protected $fillable = ['name','greeting_file','timeout','max_invalid','keypresses','timeout_action','invalid_action','active','mikopbx_id'];
    protected $casts    = ['keypresses' => 'array', 'active' => 'boolean'];
}

class Conference extends Model
{
    protected $table    = 'mikopbx_conferences';
    protected $fillable = ['bridge_id','name','status','recording_name','participants','started_at','ended_at','duration'];
    protected $casts    = ['participants' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
}
