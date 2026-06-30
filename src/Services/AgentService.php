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
     *
     * IMPORTANT — WebRTC (-WS) extensions:
     * The MikoPBX peer status endpoint often does not cleanly reflect
     * WebRTC/-WS registrations the same way it does for desk phones.
     * To avoid the web dialer flickering offline on every poll, we only
     * let the AMI-derived status OVERWRITE the local status if the local
     * status was NOT updated recently by the browser itself
     * (see AgentController::webDialerStatus). A "recent" browser update
     * is trusted for 90 seconds — after that, AMI/SIP poll data takes over
     * again (e.g. if the browser tab was closed without firing offline).
     */
    private const BROWSER_STATUS_TRUST_SECONDS = 90;

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

            // If the browser reported a status in the last N seconds, trust it
            // and skip the AMI overwrite (prevents flicker on WebRTC peers).
            $recentlyReportedByBrowser = $agent->last_seen_at
                && $agent->last_seen_at->gt(now()->subSeconds(self::BROWSER_STATUS_TRUST_SECONDS));

            if ($recentlyReportedByBrowser) {
                return $agent;
            }

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

                $rawName = $item['text'] ?? $extNum;
                $name    = strip_tags($rawName);
                $name    = preg_replace('/^' . preg_quote($extNum, '/') . '\s+/', '', $name);
                $name    = trim($name) ?: $extNum;

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
        Extension::where('extension', $extension)->update([
            'status'       => $status,
            'last_seen_at' => $status !== 'offline' ? now() : now(),
        ]);
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
