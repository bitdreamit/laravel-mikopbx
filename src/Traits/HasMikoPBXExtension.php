<?php

namespace BitDreamIT\MikoPBX\Traits;

use BitDreamIT\MikoPBX\Facades\MikoPBX;

/**
 * HasMikoPBXExtension
 *
 * Add this trait to your User model to link users to MikoPBX extensions.
 *
 *   use BitDreamIT\MikoPBX\Traits\HasMikoPBXExtension;
 *   class User extends Authenticatable {
 *       use HasMikoPBXExtension;
 *   }
 */
trait HasMikoPBXExtension
{
    /**
     * The column on your users table that stores the SIP extension number.
     * Override by defining $pbxExtensionColumn on your model.
     */
    public function getPbxExtensionColumn(): string
    {
        return property_exists($this, 'pbxExtensionColumn')
            ? $this->pbxExtensionColumn
            : 'pbx_extension';
    }

    public function getPbxExtension(): ?string
    {
        return $this->{$this->getPbxExtensionColumn()} ?? null;
    }

    /**
     * Originate a call FROM this user's extension TO a number.
     */
    public function callNumber(string $to): array
    {
        $ext = $this->getPbxExtension();

        if (! $ext) {
            throw new \RuntimeException("User [{$this->id}] has no PBX extension assigned.");
        }

        return MikoPBX::originate($ext, $to);
    }

    /**
     * Get all call logs for this user's extension.
     */
    public function callLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            \BitDreamIT\MikoPBX\Models\CallLog::class,
            'extension',
            $this->getPbxExtensionColumn()
        );
    }

    /**
     * Get pending callbacks assigned to this user's extension.
     */
    public function pendingCallbacks(): \Illuminate\Database\Eloquent\Collection
    {
        $ext = \BitDreamIT\MikoPBX\Models\Extension::where('email', $this->email)->first();
        if (! $ext) return collect();

        return \BitDreamIT\MikoPBX\Models\Callback::where('assigned_to', $ext->id)
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->get();
    }
}
