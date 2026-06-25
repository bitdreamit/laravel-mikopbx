<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\Extension;

class AgentService
{
    public function __construct(private RestApiService $api) {}

    /**
     * Get all agents from local DB, merged with live SIP status from MikoPBX.
     * Uses GET /pbxcore/api/v3/sip:getPeersStatuses for live state.
     *
     * SipPeerStatus fields from API: id, state, useragent, ipaddress, port
     * state values: OK | UNREACHABLE | LAGGED | UNKNOWN | OFF | REGISTERED
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        $agents = Extension::orderBy('extension')->get();

        try {
            $response = $this->api->getExtensionStatuses();
            $statuses = collect($response['data'] ?? [])
                ->keyBy('id');     // keyed by SIP peer ID (usually the extension number)
        } catch (\Throwable) {
            $statuses = collect();
        }

        return $agents->map(function ($agent) use ($statuses) {
            $live = $statuses->get($agent->sip_peer ?? $agent->extension);

            if ($live) {
                // Map MikoPBX v3 SIP state to our status values
                $agent->status = match(strtoupper($live['state'] ?? '')) {
                    'OK', 'REGISTERED'  => $agent->status === 'busy' ? 'busy' : 'online',
                    'UNREACHABLE',
                    'UNKNOWN',
                    'OFF'               => 'offline',
                    'LAGGED'            => 'away',
                    default             => $agent->status,
                };
            }

            return $agent;
        });
    }

    /**
     * Sync extensions from MikoPBX into local database.
     * Uses GET /pbxcore/api/v3/extensions:getForSelect
     *
     * Response items: { value: "101", text: "101 John Smith" }
     * Also syncs from GET /pbxcore/api/v3/extensions for full data.
     */
    public function sync(): int
    {
        $count = 0;

        // Primary: use getForSelect for extension numbers
        try {
            $selectItems = $this->api->getExtensions()['data'] ?? [];

            foreach ($selectItems as $item) {
                // { value: "101", text: "101 John Smith" }
                $extNum = $item['value'] ?? '';
                $name   = $item['text']  ?? $extNum;

                if (empty($extNum)) continue;

                // Strip extension number prefix from display name if present
                // e.g. "101 John Smith" → "John Smith"
                $name = preg_replace('/^\d+\s+/', '', $name) ?: $name;

                Extension::updateOrCreate(
                    ['extension' => $extNum],
                    [
                        'name'     => $name,
                        'sip_peer' => $extNum,
                        'active'   => true,
                    ]
                );
                $count++;
            }
        } catch (\Throwable $e) {
            // Fall through to try full list
        }

        // If no results, try full extension list
        if ($count === 0) {
            try {
                $fullList = $this->api->getExtensionsList()['data'] ?? [];

                foreach ($fullList as $ext) {
                    $extNum = $ext['number'] ?? $ext['extension'] ?? $ext['id'] ?? '';
                    if (empty($extNum)) continue;

                    Extension::updateOrCreate(
                        ['extension' => $extNum],
                        [
                            'name'   => $ext['name'] ?? $ext['username'] ?? $extNum,
                            'email'  => $ext['email'] ?? null,
                            'mobile' => $ext['mobile'] ?? null,
                            'active' => true,
                        ]
                    );
                    $count++;
                }
            } catch (\Throwable) {}
        }

        return $count;
    }

    public function setStatus(string $extension, string $status): void
    {
        Extension::where('extension', $extension)->update(['status' => $status]);
    }

    public function getOnlineCount(): int
    {
        return Extension::whereIn('status', ['online', 'busy'])->count();
    }

    public function getAvailableAgents(): \Illuminate\Database\Eloquent\Collection
    {
        return Extension::where('status', 'online')->where('active', true)->get();
    }
}
