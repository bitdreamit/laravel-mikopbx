<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Exceptions\MikoPBXException;

class RestApiService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['url'], '/');
        $this->apiKey  = $config['api_key'];
    }

    // ─────────────────────────────────────────
    // HTTP CLIENT
    // ─────────────────────────────────────────

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'X-Auth-Token' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])
        ->withoutVerifying()
        ->timeout(30);
    }

    private function handleResponse(Response $response, string $action): array
    {
        if ($response->failed()) {
            Log::error("MikoPBX API error [$action]", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new MikoPBXException("MikoPBX API error [$action]: " . $response->status());
        }
        return $response->json() ?? [];
    }

    // ─────────────────────────────────────────
    // SYSTEM
    // ─────────────────────────────────────────

    public function getVersion(): array
    {
        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/system/getPbxVersion"),
            'getVersion'
        );
    }

    public function getSystemStatus(): array
    {
        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/system/getInfo"),
            'getSystemStatus'
        );
    }

    // ─────────────────────────────────────────
    // CALLS
    // ─────────────────────────────────────────

    public function originate(string $from, string $to): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/sip/originate", [
                'from' => $from,
                'to'   => $to,
            ]),
            'originate'
        );
    }

    public function transfer(string $channel, string $extension): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/sip/transfer", [
                'channel' => $channel,
                'to'      => $extension,
            ]),
            'transfer'
        );
    }

    public function hangup(string $channel): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/sip/hangup", [
                'channel' => $channel,
            ]),
            'hangup'
        );
    }

    public function getActiveCalls(): array
    {
        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/cdr/getActiveCalls"),
            'getActiveCalls'
        );
    }

    public function getExtensionStatuses(): array
    {
        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/sip/getPeerStatuses"),
            'getExtensionStatuses'
        );
    }

    // ─────────────────────────────────────────
    // RECORDINGS
    // ─────────────────────────────────────────

    public function getRecordings(string $dateFrom, string $dateTo, ?string $extension = null): array
    {
        $params = ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];
        if ($extension) $params['number'] = $extension;

        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/cdr/getRecords", $params),
            'getRecordings'
        );
    }

    public function getRecordingUrl(string $filename): string
    {
        return "{$this->baseUrl}/pbxcore/api/cdr/downloadRecord?filename={$filename}";
    }

    // ─────────────────────────────────────────
    // EXTENSIONS
    // ─────────────────────────────────────────

    public function getExtensions(): array
    {
        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/extensions/getForSelect"),
            'getExtensions'
        );
    }

    // ─────────────────────────────────────────
    // CAMPAIGN / AUTO DIALER
    // ─────────────────────────────────────────

    public function uploadAudio(string $filePath): array
    {
        return $this->handleResponse(
            Http::withHeaders(['X-Auth-Token' => $this->apiKey])
                ->withoutVerifying()
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post("{$this->baseUrl}/pbxcore/api/module-dialer/v1/audio"),
            'uploadAudio'
        );
    }

    public function createDialerTask(array $data): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/module-dialer/v1/task", $data),
            'createDialerTask'
        );
    }

    public function startDialerTask(int $id): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/module-dialer/v1/task/start", ['id' => $id]),
            'startDialerTask'
        );
    }

    public function stopDialerTask(int $id): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/module-dialer/v1/task/stop", ['id' => $id]),
            'stopDialerTask'
        );
    }

    public function getDialerTaskStatus(int $id): array
    {
        return $this->handleResponse(
            $this->http()->get("{$this->baseUrl}/pbxcore/api/module-dialer/v1/task/status", ['id' => $id]),
            'getDialerTaskStatus'
        );
    }

    public function createPolling(array $data): array
    {
        return $this->handleResponse(
            $this->http()->post("{$this->baseUrl}/pbxcore/api/module-dialer/v1/polling", $data),
            'createPolling'
        );
    }
}
