<?php
namespace BitDreamIT\MikoPBX\Services;
use Illuminate\Support\Facades\Http;

class ARIService
{
    private string $base;

    public function __construct()
    {
        $this->base = rtrim(config('mikopbx.ari.url', ''), '/');
    }

    private function http()
    {
        return Http::withBasicAuth(
            config('mikopbx.ari.username'),
            config('mikopbx.ari.password')
        )->withoutVerifying()->timeout(10);
    }

    public function getChannels(): array
    {
        return $this->http()->get("{$this->base}/ari/channels")->json() ?? [];
    }

    public function hangupChannel(string $channelId): array
    {
        return $this->http()->delete("{$this->base}/ari/channels/{$channelId}")->json() ?? [];
    }

    public function originateChannel(string $endpoint, string $app, array $opts = []): array
    {
        return $this->http()->post("{$this->base}/ari/channels", array_merge([
            'endpoint' => $endpoint,
            'app'      => $app,
        ], $opts))->json() ?? [];
    }

    public function playMedia(string $channelId, string $media): array
    {
        return $this->http()->post("{$this->base}/ari/channels/{$channelId}/play", [
            'media' => $media,
        ])->json() ?? [];
    }

    public function getBridges(): array
    {
        return $this->http()->get("{$this->base}/ari/bridges")->json() ?? [];
    }

    public function getWebSocketUrl(): string
    {
        $app = config('mikopbx.ari.app', 'laravel-mikopbx');
        $user = config('mikopbx.ari.username');
        $pass = config('mikopbx.ari.password');
        $base = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $this->base);
        return "{$base}/ari/events?api_key={$user}:{$pass}&app={$app}";
    }
}
