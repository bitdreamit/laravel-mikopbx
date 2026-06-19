<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\Blacklist;

/**
 * Blacklist Service
 * Block specific numbers from calling your PBX.
 */
class BlacklistService
{
    public function block(string $number, string $reason = '', ?string $expiresAt = null): Blacklist
    {
        return Blacklist::updateOrCreate(
            ['number' => $this->normalize($number)],
            ['reason' => $reason, 'active' => true, 'expires_at' => $expiresAt]
        );
    }

    public function unblock(string $number): bool
    {
        return (bool) Blacklist::where('number', $this->normalize($number))->delete();
    }

    public function isBlocked(string $number): bool
    {
        return Blacklist::where('number', $this->normalize($number))
            ->where('active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return Blacklist::where('active', true)->orderBy('created_at', 'desc')->get();
    }

    public function cleanExpired(): int
    {
        return Blacklist::where('expires_at', '<', now())->delete();
    }

    private function normalize(string $number): string
    {
        return preg_replace('/[^0-9+]/', '', $number);
    }
}
