<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Exceptions\MikoPBXException;

/**
 * ARI — Asterisk REST Interface
 * For advanced channel control, media playback,
 * recordings, bridges, and Stasis applications.
 */
class ARIService
{
    private string $baseUrl;
    private string $username;
    private string $secret;

    public function __construct(array $config)
    {
        $this->baseUrl  = rtrim($config['ari_url'], '/');
        $this->username = $config['ari_username'];
        $this->secret   = $config['ari_secret'];
    }

    // ─────────────────────────────────────────
    // HTTP
    // ─────────────────────────────────────────

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->username, $this->secret)
            ->withoutVerifying()
            ->timeout(30)
            ->acceptJson();
    }

    private function get(string $path, array $params = []): array
    {
        $r = $this->http()->get("{$this->baseUrl}/ari{$path}", $params);
        if ($r->failed()) throw new MikoPBXException("ARI GET $path failed: " . $r->status());
        return $r->json() ?? [];
    }

    private function post(string $path, array $data = []): array
    {
        $r = $this->http()->post("{$this->baseUrl}/ari{$path}", $data);
        if ($r->failed()) throw new MikoPBXException("ARI POST $path failed: " . $r->status());
        return $r->json() ?? [];
    }

    private function delete(string $path): array
    {
        $r = $this->http()->delete("{$this->baseUrl}/ari{$path}");
        if ($r->failed()) throw new MikoPBXException("ARI DELETE $path failed: " . $r->status());
        return $r->json() ?? [];
    }

    // ─────────────────────────────────────────
    // CHANNELS
    // ─────────────────────────────────────────

    /** List all active channels */
    public function getChannels(): array
    {
        return $this->get('/channels');
    }

    /** Get specific channel details */
    public function getChannel(string $channelId): array
    {
        return $this->get("/channels/{$channelId}");
    }

    /** Originate a new outbound call */
    public function originateChannel(
        string $endpoint,
        string $extension,
        string $context     = 'from-internal',
        string $callerId    = '',
        int    $timeout     = 30,
        array  $variables   = []
    ): array {
        return $this->post('/channels', [
            'endpoint'  => $endpoint,
            'extension' => $extension,
            'context'   => $context,
            'priority'  => 1,
            'callerId'  => $callerId,
            'timeout'   => $timeout,
            'variables' => $variables,
        ]);
    }

    /** Hangup a channel with cause */
    public function hangupChannel(string $channelId, string $reason = 'normal'): array
    {
        return $this->delete("/channels/{$channelId}?reason={$reason}");
    }

    /** Hold a channel */
    public function holdChannel(string $channelId): array
    {
        return $this->post("/channels/{$channelId}/hold");
    }

    /** Unhold a channel */
    public function unholdChannel(string $channelId): array
    {
        $r = $this->http()->delete("{$this->baseUrl}/ari/channels/{$channelId}/hold");
        return $r->json() ?? [];
    }

    /** Mute a channel */
    public function muteChannel(string $channelId, string $direction = 'both'): array
    {
        return $this->post("/channels/{$channelId}/mute", ['direction' => $direction]);
    }

    /** Unmute a channel */
    public function unmuteChannel(string $channelId, string $direction = 'both'): array
    {
        $r = $this->http()->delete("{$this->baseUrl}/ari/channels/{$channelId}/mute?direction={$direction}");
        return $r->json() ?? [];
    }

    /** Play audio on channel */
    public function playAudio(string $channelId, string $media, string $lang = 'en'): array
    {
        return $this->post("/channels/{$channelId}/play", [
            'media'    => "sound:{$media}",
            'lang'     => $lang,
        ]);
    }

    /** Play custom audio file on channel */
    public function playFile(string $channelId, string $filePath): array
    {
        return $this->post("/channels/{$channelId}/play", [
            'media' => "recording:{$filePath}",
        ]);
    }

    /** Send DTMF digits to channel */
    public function sendDTMF(string $channelId, string $dtmf, int $duration = 100): array
    {
        return $this->post("/channels/{$channelId}/dtmfsend", [
            'dtmf'     => $dtmf,
            'duration' => $duration,
        ]);
    }

    /** Redirect channel to dialplan */
    public function redirectChannel(string $channelId, string $context, string $extension, int $priority = 1): array
    {
        return $this->post("/channels/{$channelId}/redirect", [
            'endpoint' => "Local/{$extension}@{$context}",
        ]);
    }

    /** Set channel variable */
    public function setChannelVar(string $channelId, string $variable, string $value): array
    {
        return $this->post("/channels/{$channelId}/variable", [
            'variable' => $variable,
            'value'    => $value,
        ]);
    }

    /** Get channel variable */
    public function getChannelVar(string $channelId, string $variable): string
    {
        $r = $this->get("/channels/{$channelId}/variable", ['variable' => $variable]);
        return $r['value'] ?? '';
    }

    // ─────────────────────────────────────────
    // BRIDGES (Conference / Connect calls)
    // ─────────────────────────────────────────

    /** Create a new bridge (conference room) */
    public function createBridge(string $type = 'mixing', string $name = ''): array
    {
        return $this->post('/bridges', [
            'type' => $type,
            'name' => $name ?: 'bridge-' . now()->timestamp,
        ]);
    }

    /** Get all active bridges */
    public function getBridges(): array
    {
        return $this->get('/bridges');
    }

    /** Get specific bridge */
    public function getBridge(string $bridgeId): array
    {
        return $this->get("/bridges/{$bridgeId}");
    }

    /** Add channel to bridge */
    public function addChannelToBridge(string $bridgeId, string $channelId, string $role = 'participant'): array
    {
        return $this->post("/bridges/{$bridgeId}/addChannel", [
            'channel' => $channelId,
            'role'    => $role,
        ]);
    }

    /** Remove channel from bridge */
    public function removeChannelFromBridge(string $bridgeId, string $channelId): array
    {
        return $this->post("/bridges/{$bridgeId}/removeChannel", [
            'channel' => $channelId,
        ]);
    }

    /** Destroy bridge */
    public function destroyBridge(string $bridgeId): array
    {
        return $this->delete("/bridges/{$bridgeId}");
    }

    /** Play audio to entire bridge (all participants hear it) */
    public function playAudioToBridge(string $bridgeId, string $media): array
    {
        return $this->post("/bridges/{$bridgeId}/play", [
            'media' => "sound:{$media}",
        ]);
    }

    /** Start recording a bridge (conference) */
    public function recordBridge(
        string $bridgeId,
        string $name,
        string $format          = 'wav',
        bool   $beep            = true,
        string $ifExists        = 'overwrite'
    ): array {
        return $this->post("/bridges/{$bridgeId}/record", [
            'name'     => $name,
            'format'   => $format,
            'beep'     => $beep,
            'ifExists' => $ifExists,
        ]);
    }

    // ─────────────────────────────────────────
    // RECORDINGS
    // ─────────────────────────────────────────

    /** Start recording a channel */
    public function startRecording(
        string $channelId,
        string $name,
        string $format   = 'wav',
        bool   $beep     = false
    ): array {
        return $this->post("/channels/{$channelId}/record", [
            'name'     => $name,
            'format'   => $format,
            'beep'     => $beep,
            'ifExists' => 'overwrite',
        ]);
    }

    /** Stop recording */
    public function stopRecording(string $recordingName): array
    {
        return $this->post("/recordings/live/{$recordingName}/stop");
    }

    /** Pause recording */
    public function pauseRecording(string $recordingName): array
    {
        return $this->post("/recordings/live/{$recordingName}/pause");
    }

    /** Resume recording */
    public function resumeRecording(string $recordingName): array
    {
        $r = $this->http()->delete("{$this->baseUrl}/ari/recordings/live/{$recordingName}/pause");
        return $r->json() ?? [];
    }

    /** List stored recordings */
    public function getStoredRecordings(): array
    {
        return $this->get('/recordings/stored');
    }

    /** Delete a stored recording */
    public function deleteRecording(string $recordingName): array
    {
        return $this->delete("/recordings/stored/{$recordingName}");
    }

    // ─────────────────────────────────────────
    // SOUNDS
    // ─────────────────────────────────────────

    /** List all available system sounds */
    public function getSounds(?string $lang = null): array
    {
        $params = $lang ? ['lang' => $lang] : [];
        return $this->get('/sounds', $params);
    }

    // ─────────────────────────────────────────
    // ENDPOINTS (Extensions/Peers)
    // ─────────────────────────────────────────

    /** List all endpoints */
    public function getEndpoints(): array
    {
        return $this->get('/endpoints');
    }

    /** Get endpoint details */
    public function getEndpoint(string $tech, string $resource): array
    {
        return $this->get("/endpoints/{$tech}/{$resource}");
    }

    /** List PJSIP endpoints */
    public function getPJSIPEndpoints(): array
    {
        return $this->get('/endpoints/PJSIP');
    }

    // ─────────────────────────────────────────
    // ASTERISK SYSTEM
    // ─────────────────────────────────────────

    /** Get Asterisk system info */
    public function getAsteriskInfo(): array
    {
        return $this->get('/asterisk/info');
    }

    /** Get Asterisk config section */
    public function getConfig(string $configClass, string $objectType): array
    {
        return $this->get("/asterisk/config/dynamic/{$configClass}/{$objectType}");
    }

    /** Send AMI action via ARI */
    public function sendAMIAction(string $action, array $parameters = []): array
    {
        return $this->post('/asterisk/ami_action', array_merge(
            ['action' => $action],
            $parameters
        ));
    }

    /** Reload Asterisk module */
    public function reloadModule(string $module): array
    {
        return $this->post("/asterisk/modules/{$module}");
    }

    /** Get current Asterisk log channels */
    public function getLogChannels(): array
    {
        return $this->get('/asterisk/logging');
    }

    // ─────────────────────────────────────────
    // PLAYBACKS
    // ─────────────────────────────────────────

    /** Get playback info */
    public function getPlayback(string $playbackId): array
    {
        return $this->get("/playbacks/{$playbackId}");
    }

    /** Stop a playback */
    public function stopPlayback(string $playbackId): array
    {
        return $this->delete("/playbacks/{$playbackId}");
    }

    /** Control playback (restart, pause, unpause, forward, reverse) */
    public function controlPlayback(string $playbackId, string $operation): array
    {
        return $this->post("/playbacks/{$playbackId}/control", [
            'operation' => $operation,
        ]);
    }

    // ─────────────────────────────────────────
    // WEBSOCKET EVENTS (for real-time)
    // ─────────────────────────────────────────

    /** Build WebSocket URL for ARI events */
    public function getWebSocketUrl(string $app = 'laravel-mikopbx'): string
    {
        $base = str_replace('http', 'ws', $this->baseUrl);
        return "{$base}/ari/events?api_key={$this->username}:{$this->secret}&app={$app}&subscribeAll=true";
    }
}
