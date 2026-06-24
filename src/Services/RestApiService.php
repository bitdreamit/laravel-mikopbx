<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use BitDreamIT\MikoPBX\Exceptions\MikoPBXException;

class RestApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('mikopbx.url', ''), '/');
        $this->apiKey  = config('mikopbx.api_key', '');
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])
        ->withoutVerifying()
        ->timeout(config('mikopbx.timeout', 10));
    }

    private function get(string $path, array $params = []): array
    {
        $r = $this->http()->get("{$this->baseUrl}{$path}", $params);
        if ($r->failed()) throw new MikoPBXException("MikoPBX API error [{$r->status()}] GET {$path}");
        return $r->json() ?? [];
    }

    private function post(string $path, array $data = []): array
    {
        $r = $this->http()->post("{$this->baseUrl}{$path}", $data);
        if ($r->failed()) throw new MikoPBXException("MikoPBX API error [{$r->status()}] POST {$path}");
        return $r->json() ?? [];
    }

    // ── Calls ────────────────────────────────────────────────────────────────

    /** Originate an outbound call from extension to number */
    public function originate(string $from, string $to, array $opts = []): array
    {
        return $this->post('/pbxcore/api/sip/originate', array_merge([
            'from' => $from,
            'to'   => $to,
        ], $opts));
    }

    /** Transfer active channel to extension */
    public function transfer(string $channel, string $to, string $context = 'from-internal'): array
    {
        return $this->post('/pbxcore/api/sip/transfer', [
            'channel' => $channel,
            'to'      => $to,
            'context' => $context,
        ]);
    }

    /** Hangup a channel */
    public function hangup(string $channel): array
    {
        return $this->post('/pbxcore/api/sip/hangup', ['channel' => $channel]);
    }

    /** Mute/unmute a channel */
    public function mute(string $channel, bool $mute = true): array
    {
        return $this->post('/pbxcore/api/sip/mute', ['channel' => $channel, 'mute' => $mute]);
    }

    /** Get all active calls right now */
    public function getActiveCalls(): array
    {
        return $this->get('/pbxcore/api/cdr/getActiveCalls');
    }

    /** Get CDR records */
    public function getCDR(string $from, string $to, array $filters = []): array
    {
        return $this->get('/pbxcore/api/cdr/getRecords', array_merge([
            'dateFrom' => $from,
            'dateTo'   => $to,
        ], $filters));
    }

    // ── Extensions ───────────────────────────────────────────────────────────

    public function getExtensions(): array
    {
        return $this->get('/pbxcore/api/extensions/getForSelect');
    }

    public function getExtensionStatuses(): array
    {
        return $this->get('/pbxcore/api/sip/getPeerStatuses');
    }

    public function createExtension(array $data): array
    {
        return $this->post('/pbxcore/api/extensions/saveRecord', $data);
    }

    public function deleteExtension(string $id): array
    {
        return $this->post('/pbxcore/api/extensions/deleteRecord', ['id' => $id]);
    }

    // ── SIP Trunks ───────────────────────────────────────────────────────────

    public function getTrunks(): array
    {
        return $this->get('/pbxcore/api/providers/getPbxSettings');
    }

    public function getTrunkStatus(): array
    {
        return $this->get('/pbxcore/api/sip/getRegistry');
    }

    // ── Recordings ───────────────────────────────────────────────────────────

    public function getRecordings(string $from, string $to, string $number = ''): array
    {
        return $this->get('/pbxcore/api/cdr/getRecords', [
            'dateFrom' => $from,
            'dateTo'   => $to,
            'number'   => $number,
        ]);
    }

    public function getRecordingUrl(string $filename): string
    {
        return "{$this->baseUrl}/pbxcore/api/cdr/playback?filename={$filename}";
    }

    // ── Auto Dialer ──────────────────────────────────────────────────────────

    public function uploadAudio(string $filePath): array
    {
        $r = $this->http()
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post("{$this->baseUrl}/pbxcore/api/module-dialer/v1/audio");
        return $r->json() ?? [];
    }

    public function createDialerTask(array $data): array
    {
        return $this->post('/pbxcore/api/module-dialer/v1/task', $data);
    }

    public function startDialerTask(int $id): array
    {
        return $this->post('/pbxcore/api/module-dialer/v1/task/start', ['id' => $id]);
    }

    public function stopDialerTask(int $id): array
    {
        return $this->post('/pbxcore/api/module-dialer/v1/task/stop', ['id' => $id]);
    }

    public function pauseDialerTask(int $id): array
    {
        return $this->post('/pbxcore/api/module-dialer/v1/task/pause', ['id' => $id]);
    }

    public function getDialerTaskStatus(int $id): array
    {
        return $this->get('/pbxcore/api/module-dialer/v1/task/status', ['id' => $id]);
    }

    public function getDialerTasks(): array
    {
        return $this->get('/pbxcore/api/module-dialer/v1/task');
    }

    public function createPolling(array $script): array
    {
        return $this->post('/pbxcore/api/module-dialer/v1/polling', $script);
    }

    // ── IVR ──────────────────────────────────────────────────────────────────

    public function getIVRMenus(): array
    {
        return $this->get('/pbxcore/api/ivrMenu/getForSelect');
    }

    public function saveIVRMenu(array $data): array
    {
        return $this->post('/pbxcore/api/ivrMenu/saveRecord', $data);
    }

    // ── Conference ───────────────────────────────────────────────────────────

    public function getConferenceRooms(): array
    {
        return $this->get('/pbxcore/api/conferenceRooms/getForSelect');
    }

    public function saveConferenceRoom(array $data): array
    {
        return $this->post('/pbxcore/api/conferenceRooms/saveRecord', $data);
    }

    // ── System ───────────────────────────────────────────────────────────────

    public function getSystemInfo(): array
    {
        return $this->get('/pbxcore/api/system/getInfo');
    }

    public function applyConfig(): array
    {
        return $this->post('/pbxcore/api/system/applyConfig');
    }

    public function getSoundFiles(): array
    {
        return $this->get('/pbxcore/api/soundFiles/getForSelect');
    }
}
