<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use BitDreamIT\MikoPBX\Exceptions\MikoPBXException;

/**
 * RestApiService — wraps the MikoPBX REST API v3.
 *
 * Base URL : https://your-mikopbx-ip/pbxcore/api/v3/
 * Auth     : Authorization: Bearer {JWT API key}
 *
 * IMPORTANT: originate / transfer / hangup / mute are NOT in REST v3.
 * Those actions must go through AMIService (TCP port 5038).
 */
class RestApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('mikopbx.url', ''), '/');
        $this->apiKey  = config('mikopbx.api_key', '');
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────────

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",   // ✅ Correct v3 auth header
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])
        ->withoutVerifying()
        ->timeout(config('mikopbx.timeout', 10));
    }

    private function get(string $path, array $params = []): array
    {
        $r = $this->http()->get("{$this->baseUrl}{$path}", $params);
        if ($r->failed()) {
            throw new MikoPBXException("MikoPBX API error [{$r->status()}] GET {$path}: " . $r->body());
        }
        return $r->json() ?? [];
    }

    private function post(string $path, array $data = []): array
    {
        $r = $this->http()->post("{$this->baseUrl}{$path}", $data);
        if ($r->failed()) {
            throw new MikoPBXException("MikoPBX API error [{$r->status()}] POST {$path}: " . $r->body());
        }
        return $r->json() ?? [];
    }

    private function put(string $path, array $data = []): array
    {
        $r = $this->http()->put("{$this->baseUrl}{$path}", $data);
        if ($r->failed()) {
            throw new MikoPBXException("MikoPBX API error [{$r->status()}] PUT {$path}: " . $r->body());
        }
        return $r->json() ?? [];
    }

    private function delete(string $path): array
    {
        $r = $this->http()->delete("{$this->baseUrl}{$path}");
        if ($r->failed()) {
            throw new MikoPBXException("MikoPBX API error [{$r->status()}] DELETE {$path}: " . $r->body());
        }
        return $r->json() ?? [];
    }

    // ── PBX Status / Active Calls ────────────────────────────────────────────

    /**
     * Get all currently active calls in real time.
     * Endpoint: GET /pbxcore/api/v3/pbx-status:getActiveCalls
     */
    public function getActiveCalls(): array
    {
        return $this->get('/pbxcore/api/v3/pbx-status:getActiveCalls');
    }

    /**
     * Get all active Asterisk channels.
     * Endpoint: GET /pbxcore/api/v3/pbx-status:getActiveChannels
     */
    public function getActiveChannels(): array
    {
        return $this->get('/pbxcore/api/v3/pbx-status:getActiveChannels');
    }

    // ── CDR (Call Detail Records) ────────────────────────────────────────────

    /**
     * Get CDR records with filtering and pagination.
     * Endpoint: GET /pbxcore/api/v3/cdr
     *
     * Response fields per record:
     *   id, start, endtime, answer, src_num, dst_num, src_chan, dst_chan,
     *   UNIQUEID, linkedid, disposition, duration, billsec, recordingfile,
     *   playback_url, download_url, dtmf_digits, dialstatus, transfer
     *
     * @param string $from        e.g. "2026-06-01 00:00:00"
     * @param string $to          e.g. "2026-06-30 23:59:59"
     * @param array  $filters     Optional: src_num, dst_num, disposition, limit, offset
     */
    public function getCDR(string $from, string $to, array $filters = []): array
    {
        return $this->get('/pbxcore/api/v3/cdr', array_merge([
            'dateFrom' => $from,
            'dateTo'   => $to,
            'limit'    => $filters['limit']  ?? 100,
            'offset'   => $filters['offset'] ?? 0,
        ], array_diff_key($filters, ['limit' => 0, 'offset' => 0])));
    }

    /**
     * Get a single CDR record by its ID.
     * Endpoint: GET /pbxcore/api/v3/cdr/{id}
     */
    public function getCDRById(int $id): array
    {
        return $this->get("/pbxcore/api/v3/cdr/{$id}");
    }

    /**
     * Delete a CDR record.
     * Endpoint: DELETE /pbxcore/api/v3/cdr/{id}
     */
    public function deleteCDR(int $id): array
    {
        return $this->delete("/pbxcore/api/v3/cdr/{$id}");
    }

    /**
     * Get call recordings (CDR records that have a recordingfile).
     * Uses GET /pbxcore/api/v3/cdr with src_num / dst_num filter.
     *
     * @param string $from    Date from
     * @param string $to      Date to
     * @param string $number  Optional number filter (searches src_num or dst_num separately)
     */
    public function getRecordings(string $from, string $to, string $number = ''): array
    {
        $params = [
            'dateFrom' => $from,
            'dateTo'   => $to,
            'limit'    => 200,
        ];
        if ($number) {
            // API filters by src_num or dst_num; pass src_num — caller side
            $params['src_num'] = $number;
        }
        $response = $this->get('/pbxcore/api/v3/cdr', $params);

        // Only return records that have a recording file
        $data = $response['data'] ?? $response;
        if (is_array($data)) {
            $response['data'] = array_values(array_filter(
                $data,
                fn($r) => ! empty($r['recordingfile']) || ! empty($r['playback_url'])
            ));
        }
        return $response;
    }

    /**
     * Get playback URL for a CDR recording.
     * The API returns pre-signed playback_url and download_url in each CDR record.
     * This method builds the proxy URL for use when only filename is known.
     * Endpoint: GET /pbxcore/api/v3/cdr:playback
     */
    public function getRecordingPlaybackUrl(int $cdrId): string
    {
        return "{$this->baseUrl}/pbxcore/api/v3/cdr:playback?id={$cdrId}&token={$this->apiKey}";
    }

    /**
     * @deprecated Use getRecordingPlaybackUrl($cdrId) — kept for backward compat.
     */
    public function getRecordingUrl(string $filename): string
    {
        return "{$this->baseUrl}/pbxcore/api/v3/cdr:playback?filename=" . urlencode($filename);
    }

    /**
     * Get CDR metadata (column definitions).
     * Endpoint: GET /pbxcore/api/v3/cdr:getMetadata
     */
    public function getCDRMetadata(): array
    {
        return $this->get('/pbxcore/api/v3/cdr:getMetadata');
    }

    /**
     * Get CDR stats grouped by SIP provider.
     * Endpoint: GET /pbxcore/api/v3/cdr:getStatsByProvider
     */
    public function getCDRStatsByProvider(string $from, string $to): array
    {
        return $this->get('/pbxcore/api/v3/cdr:getStatsByProvider', [
            'dateFrom' => $from,
            'dateTo'   => $to,
        ]);
    }

    // ── Extensions ───────────────────────────────────────────────────────────

    /**
     * Get all extensions as {value, text} pairs for dropdowns.
     * Endpoint: GET /pbxcore/api/v3/extensions:getForSelect
     * Response: data = [{"value": "101", "text": "101 John Smith"}, ...]
     */
    public function getExtensions(): array
    {
        return $this->get('/pbxcore/api/v3/extensions:getForSelect');
    }

    /**
     * Get full extension list with all fields.
     * Endpoint: GET /pbxcore/api/v3/extensions
     */
    public function getExtensionsList(): array
    {
        return $this->get('/pbxcore/api/v3/extensions');
    }

    /**
     * Get single extension detail.
     * Endpoint: GET /pbxcore/api/v3/extensions/{id}
     */
    public function getExtensionById(string $id): array
    {
        return $this->get("/pbxcore/api/v3/extensions/{$id}");
    }

    /**
     * Check if an extension number is available.
     * Endpoint: POST /pbxcore/api/v3/extensions:available
     */
    public function checkExtensionAvailable(string $number): array
    {
        return $this->post('/pbxcore/api/v3/extensions:available', ['number' => $number]);
    }

    // ── SIP Peer Statuses (extensions online/offline) ────────────────────────

    /**
     * Get SIP peer statuses — shows which extensions are registered/online.
     * Endpoint: GET /pbxcore/api/v3/sip:getPeersStatuses
     *
     * Response data item fields:
     *   id (string), state (OK|UNREACHABLE|LAGGED|UNKNOWN|OFF|REGISTERED),
     *   useragent, ipaddress, port
     */
    public function getExtensionStatuses(): array
    {
        return $this->get('/pbxcore/api/v3/sip:getPeersStatuses');
    }

    /**
     * Get SIP registry status for all extensions.
     * Endpoint: GET /pbxcore/api/v3/sip:getRegistry
     */
    public function getSIPRegistry(): array
    {
        return $this->get('/pbxcore/api/v3/sip:getRegistry');
    }

    /**
     * Get combined SIP statuses.
     * Endpoint: GET /pbxcore/api/v3/sip:getStatuses
     */
    public function getSIPStatuses(): array
    {
        return $this->get('/pbxcore/api/v3/sip:getStatuses');
    }

    /**
     * Get single SIP peer status.
     * Endpoint: GET /pbxcore/api/v3/sip/{id}:getStatus
     */
    public function getSIPPeerStatus(string $id): array
    {
        return $this->get("/pbxcore/api/v3/sip/{$id}:getStatus");
    }

    /**
     * Get SIP peer call statistics.
     * Endpoint: GET /pbxcore/api/v3/sip/{id}:getStats
     */
    public function getSIPPeerStats(string $id): array
    {
        return $this->get("/pbxcore/api/v3/sip/{$id}:getStats");
    }

    // ── SIP Providers / Trunks (AMARIP trunk) ───────────────────────────────

    /**
     * Get registration status of all SIP trunks.
     * Endpoint: GET /pbxcore/api/v3/sip-providers:getStatuses
     *
     * Response data item fields: id, state (REGISTERED|UNREGISTERED|...), ...
     * Use state === 'REGISTERED' to check if AMARIP trunk is up.
     */
    public function getTrunkStatus(): array
    {
        return $this->get('/pbxcore/api/v3/sip-providers:getStatuses');
    }

    /**
     * Get all SIP providers (trunks) with full config.
     * Endpoint: GET /pbxcore/api/v3/sip-providers
     */
    public function getTrunks(): array
    {
        return $this->get('/pbxcore/api/v3/sip-providers');
    }

    /**
     * Get single SIP provider detail.
     * Endpoint: GET /pbxcore/api/v3/sip-providers/{id}
     */
    public function getTrunkById(string $id): array
    {
        return $this->get("/pbxcore/api/v3/sip-providers/{$id}");
    }

    /**
     * Get single SIP provider registration status.
     * Endpoint: GET /pbxcore/api/v3/sip-providers/{id}:getStatus
     */
    public function getTrunkStatusById(string $id): array
    {
        return $this->get("/pbxcore/api/v3/sip-providers/{$id}:getStatus");
    }

    /**
     * Get SIP provider call statistics.
     * Endpoint: GET /pbxcore/api/v3/sip-providers/{id}:getStats
     */
    public function getTrunkStats(string $id): array
    {
        return $this->get("/pbxcore/api/v3/sip-providers/{$id}:getStats");
    }

    /**
     * Force re-check SIP provider registration.
     * Endpoint: POST /pbxcore/api/v3/sip-providers/{id}:forceCheck
     */
    public function forceTrunkCheck(string $id): array
    {
        return $this->post("/pbxcore/api/v3/sip-providers/{$id}:forceCheck");
    }

    // ── Employees ────────────────────────────────────────────────────────────

    /**
     * Get all employees.
     * Endpoint: GET /pbxcore/api/v3/employees
     */
    public function getEmployees(): array
    {
        return $this->get('/pbxcore/api/v3/employees');
    }

    /**
     * Get single employee.
     * Endpoint: GET /pbxcore/api/v3/employees/{id}
     */
    public function getEmployeeById(string $id): array
    {
        return $this->get("/pbxcore/api/v3/employees/{$id}");
    }

    /**
     * Create employee.
     * Endpoint: POST /pbxcore/api/v3/employees
     */
    public function createEmployee(array $data): array
    {
        return $this->post('/pbxcore/api/v3/employees', $data);
    }

    /**
     * Update employee.
     * Endpoint: PUT /pbxcore/api/v3/employees/{id}
     */
    public function updateEmployee(string $id, array $data): array
    {
        return $this->put("/pbxcore/api/v3/employees/{$id}", $data);
    }

    // ── IVR Menu ─────────────────────────────────────────────────────────────

    /**
     * Get all IVR menus.
     * Endpoint: GET /pbxcore/api/v3/ivr-menu
     */
    public function getIVRMenus(): array
    {
        return $this->get('/pbxcore/api/v3/ivr-menu');
    }

    /**
     * Get single IVR menu.
     * Endpoint: GET /pbxcore/api/v3/ivr-menu/{id}
     */
    public function getIVRMenuById(string $id): array
    {
        return $this->get("/pbxcore/api/v3/ivr-menu/{$id}");
    }

    /**
     * Create a new IVR menu.
     * Endpoint: POST /pbxcore/api/v3/ivr-menu
     */
    public function saveIVRMenu(array $data): array
    {
        if (isset($data['id'])) {
            return $this->put("/pbxcore/api/v3/ivr-menu/{$data['id']}", $data);
        }
        return $this->post('/pbxcore/api/v3/ivr-menu', $data);
    }

    /**
     * Delete IVR menu.
     * Endpoint: DELETE /pbxcore/api/v3/ivr-menu/{id}
     */
    public function deleteIVRMenu(string $id): array
    {
        return $this->delete("/pbxcore/api/v3/ivr-menu/{$id}");
    }

    /**
     * Get default IVR menu template.
     * Endpoint: GET /pbxcore/api/v3/ivr-menu:getDefault
     */
    public function getIVRMenuDefault(): array
    {
        return $this->get('/pbxcore/api/v3/ivr-menu:getDefault');
    }

    // ── Conference Rooms ─────────────────────────────────────────────────────

    /**
     * Get all conference rooms.
     * Endpoint: GET /pbxcore/api/v3/conference-rooms
     */
    public function getConferenceRooms(): array
    {
        return $this->get('/pbxcore/api/v3/conference-rooms');
    }

    /**
     * Create conference room.
     * Endpoint: POST /pbxcore/api/v3/conference-rooms
     */
    public function saveConferenceRoom(array $data): array
    {
        if (isset($data['id'])) {
            return $this->put("/pbxcore/api/v3/conference-rooms/{$data['id']}", $data);
        }
        return $this->post('/pbxcore/api/v3/conference-rooms', $data);
    }

    // ── Call Queues ──────────────────────────────────────────────────────────

    /**
     * Get all call queues.
     * Endpoint: GET /pbxcore/api/v3/call-queues
     */
    public function getCallQueues(): array
    {
        return $this->get('/pbxcore/api/v3/call-queues');
    }

    /**
     * Get single call queue.
     * Endpoint: GET /pbxcore/api/v3/call-queues/{id}
     */
    public function getCallQueueById(string $id): array
    {
        return $this->get("/pbxcore/api/v3/call-queues/{$id}");
    }

    /**
     * Create or update call queue.
     * Endpoint: POST /pbxcore/api/v3/call-queues (create) or PUT /{id} (update)
     */
    public function saveCallQueue(array $data): array
    {
        if (isset($data['id'])) {
            return $this->put("/pbxcore/api/v3/call-queues/{$data['id']}", $data);
        }
        return $this->post('/pbxcore/api/v3/call-queues', $data);
    }

    // ── Routing ──────────────────────────────────────────────────────────────

    /**
     * Get all inbound routes.
     * Endpoint: GET /pbxcore/api/v3/incoming-routes
     */
    public function getInboundRoutes(): array
    {
        return $this->get('/pbxcore/api/v3/incoming-routes');
    }

    /**
     * Get unique DID numbers from inbound routes.
     * Endpoint: GET /pbxcore/api/v3/incoming-routes:getUniqueDIDs
     */
    public function getUniqueDIDs(): array
    {
        return $this->get('/pbxcore/api/v3/incoming-routes:getUniqueDIDs');
    }

    /**
     * Get all outbound routes.
     * Endpoint: GET /pbxcore/api/v3/outbound-routes
     */
    public function getOutboundRoutes(): array
    {
        return $this->get('/pbxcore/api/v3/outbound-routes');
    }

    // ── Sound Files ──────────────────────────────────────────────────────────

    /**
     * Get sound files as {value, text} for dropdowns.
     * Endpoint: GET /pbxcore/api/v3/sound-files:getForSelect
     */
    public function getSoundFiles(): array
    {
        return $this->get('/pbxcore/api/v3/sound-files:getForSelect');
    }

    /**
     * List all sound files with full detail.
     * Endpoint: GET /pbxcore/api/v3/sound-files
     */
    public function getSoundFilesList(): array
    {
        return $this->get('/pbxcore/api/v3/sound-files');
    }

    /**
     * Upload an audio file to MikoPBX.
     * Endpoint: POST /pbxcore/api/v3/sound-files:uploadFile (multipart/form-data)
     *
     * @param string $filePath Local file path
     */
    public function uploadAudio(string $filePath): array
    {
        $r = Http::withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->withoutVerifying()
            ->timeout(30)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post("{$this->baseUrl}/pbxcore/api/v3/sound-files:uploadFile");

        if ($r->failed()) {
            throw new MikoPBXException("Sound file upload failed [{$r->status()}]: " . $r->body());
        }
        return $r->json() ?? [];
    }

    /**
     * Get playback URL for a sound file.
     * Endpoint: GET /pbxcore/api/v3/sound-files:playback
     */
    public function getSoundFilePlaybackUrl(string $fileId): string
    {
        return "{$this->baseUrl}/pbxcore/api/v3/sound-files:playback?id={$fileId}";
    }

    // ── System ───────────────────────────────────────────────────────────────

    /**
     * Get full system information (OS, disk, CPU, MikoPBX version).
     * Endpoint: GET /pbxcore/api/v3/sysinfo:getInfo
     */
    public function getSystemInfo(): array
    {
        return $this->get('/pbxcore/api/v3/sysinfo:getInfo');
    }

    /**
     * Simple ping — no auth required.
     * Endpoint: GET /pbxcore/api/v3/system:ping
     */
    public function ping(): array
    {
        return $this->get('/pbxcore/api/v3/system:ping');
    }

    /**
     * Verify the API key is valid.
     * Endpoint: GET /pbxcore/api/v3/system:checkAuth
     */
    public function checkAuth(): array
    {
        return $this->get('/pbxcore/api/v3/system:checkAuth');
    }

    /**
     * Get server datetime and timezone.
     * Endpoint: GET /pbxcore/api/v3/system:datetime
     */
    public function getDatetime(): array
    {
        return $this->get('/pbxcore/api/v3/system:datetime');
    }

    /**
     * Check for MikoPBX firmware updates.
     * Endpoint: GET /pbxcore/api/v3/system:checkForUpdates
     */
    public function checkForUpdates(): array
    {
        return $this->get('/pbxcore/api/v3/system:checkForUpdates');
    }

    // ── Off-work times ───────────────────────────────────────────────────────

    /**
     * Get all off-work time rules.
     * Endpoint: GET /pbxcore/api/v3/off-work-times
     */
    public function getOffWorkTimes(): array
    {
        return $this->get('/pbxcore/api/v3/off-work-times');
    }

    // ── API Keys ─────────────────────────────────────────────────────────────

    /**
     * List all API keys.
     * Endpoint: GET /pbxcore/api/v3/api-keys
     */
    public function getApiKeys(): array
    {
        return $this->get('/pbxcore/api/v3/api-keys');
    }

    /**
     * Get the default API key.
     * Endpoint: GET /pbxcore/api/v3/api-keys:getDefault
     */
    public function getDefaultApiKey(): array
    {
        return $this->get('/pbxcore/api/v3/api-keys:getDefault');
    }

    // ── NOTE: No REST v3 endpoints for call control ──────────────────────────
    //
    // originate()  → use AMIService::originate()   (Action: Originate)
    // transfer()   → use AMIService::redirect()    (Action: Redirect)
    // hangup()     → use AMIService::hangup()      (Action: Hangup)
    // mute()       → use AMIService::mute()        (Action: MuteAudio)
}
