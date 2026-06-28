<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\Extension;

class AgentService
{
    public function __construct(private RestApiService $api) {}

    /**
     * Get all agents from local DB, merged with live SIP status from MikoPBX.
     *
     * SIP state values from real API (sip:getPeersStatuses):
     *   OK | REGISTERED | UNREACHABLE | LAGGED | UNKNOWN | OFF
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        $agents = Extension::orderBy('extension')->get();

        try {
            $response = $this->api->getExtensionStatuses();
            $statuses = collect($response['data'] ?? [])
                ->keyBy('id');
        } catch (\Throwable) {
            $statuses = collect();
        }

        return $agents->map(function ($agent) use ($statuses) {
            $live = $statuses->get($agent->sip_peer ?? $agent->extension);

            if ($live) {
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
     * Sync extensions from MikoPBX REST API v3 into local DB.
     *
     * Real getForSelect response:
     *   data: [{"value": "101", "text": "101 John Smith"}, ...]
     *
     * Note: MikoPBX sometimes includes Semantic UI HTML in the text field.
     *   e.g. "text": "101 <i class=\"phone icon\"></i> John Smith"
     *   We strip all HTML tags before storing.
     */
    public function sync(): int
    {
        $count = 0;

        try {
            $response = $this->api->getExtensions();
            $items    = $response['data'] ?? [];

            foreach ($items as $item) {
                $extNum = trim($item['value'] ?? '');
                if (empty($extNum)) continue;

                // Strip any HTML tags MikoPBX may include in display names
                $rawName = $item['text'] ?? $extNum;
                $name    = strip_tags($rawName);

                // Remove extension number prefix from display name
                // "101 John Smith" → "John Smith"
                // "101" → "101" (keep if no name follows)
                $name = preg_replace('/^' . preg_quote($extNum, '/') . '\s+/', '', $name);
                $name = trim($name) ?: $extNum;

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
            \Illuminate\Support\Facades\Log::error(
                "MikoPBX: sync-extensions failed: " . $e->getMessage()
            );
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
