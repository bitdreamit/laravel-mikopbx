<?php
namespace BitDreamIT\MikoPBX\Services;
use BitDreamIT\MikoPBX\Models\Blacklist;

class BlacklistService
{
    public function add(string $number, string $reason = '', string $direction = 'both', ?string $expiresAt = null): Blacklist
    {
        return Blacklist::updateOrCreate(['number' => $number], [
            'reason'     => $reason,
            'direction'  => $direction,
            'expires_at' => $expiresAt,
            'created_by' => auth()->id(),
        ]);
    }

    public function remove(string $number): bool
    {
        return (bool) Blacklist::where('number', $number)->delete();
    }

    public function isBlocked(string $number, string $direction = 'inbound'): bool
    {
        return Blacklist::where('number', $number)
            ->where(fn($q) => $q->where('direction', $direction)->orWhere('direction', 'both'))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Blacklist::orderByDesc('created_at')->get();
    }
}
