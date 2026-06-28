<?php

namespace BitDreamIT\MikoPBX\Traits;

use BitDreamIT\MikoPBX\Facades\MikoPBX;

/**
 * HasMikoPBXExtension — add to your User model.
 *
 * Requires migration 000002 to add pbx_extension and pbx_sip_password to users table:
 *   php artisan mikopbx:install   (runs both migrations)
 *
 * Then assign extensions to users:
 *   $user->update(['pbx_extension' => '101', 'pbx_sip_password' => 'secret123']);
 *
 * Usage in User model:
 *   use BitDreamIT\MikoPBX\Traits\HasMikoPBXExtension;
 *   class User extends Authenticatable {
 *       use HasMikoPBXExtension;
 *       protected $fillable = [..., 'pbx_extension', 'pbx_sip_password'];
 *       protected $hidden   = [..., 'pbx_sip_password'];
 *   }
 */
trait HasMikoPBXExtension
{
    /**
     * Get this user's PBX extension number.
     * Returns null if no extension is assigned.
     */
    public function getPbxExtension(): ?string
    {
        // Priority 1: users.pbx_extension column
        if (! empty($this->pbx_extension)) {
            return (string) $this->pbx_extension;
        }

        // Priority 2: look up by email in mikopbx_extensions table
        return \BitDreamIT\MikoPBX\Models\Extension::where('email', $this->email)
            ->value('extension');
    }

    /**
     * Get this user's SIP password for WebRTC dialer.
     */
    public function getPbxSipPassword(): string
    {
        return (string) ($this->pbx_sip_password ?? '');
    }

    /**
     * Check if this user has a PBX extension assigned.
     */
    public function hasPbxExtension(): bool
    {
        return ! empty($this->getPbxExtension());
    }

    /**
     * Originate a call FROM this user's extension TO a number.
     * Uses AMI (the only way to originate in MikoPBX REST v3).
     *
     * @param string $to  Destination number e.g. "01711000000" or extension "102"
     */
    public function callNumber(string $to): array
    {
        $ext = $this->getPbxExtension();

        if (! $ext) {
            throw new \RuntimeException(
                "User [{$this->id}] has no PBX extension. " .
                "Set users.pbx_extension or add to mikopbx_extensions table."
            );
        }

        return MikoPBX::originate($ext, $to);
    }

    /**
     * Get call logs for this user's extension (from local DB).
     * Requires mikopbx:cdr-sync to have been run.
     */
    public function callLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            \BitDreamIT\MikoPBX\Models\CallLog::class,
            'extension',
            'pbx_extension'
        );
    }

    /**
     * Get pending callbacks assigned to this user's extension.
     */
    public function pendingCallbacks(): \Illuminate\Database\Eloquent\Collection
    {
        $ext = \BitDreamIT\MikoPBX\Models\Extension::where(
            'email', $this->email
        )->first();

        if (! $ext) return collect();

        return \BitDreamIT\MikoPBX\Models\Callback::where('assigned_to', $ext->id)
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->get();
    }
}
