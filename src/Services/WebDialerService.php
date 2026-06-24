<?php
namespace BitDreamIT\MikoPBX\Services;

class WebDialerService
{
    /** Get SIP.js / JsSIP config for the current user's extension */
    public function getConfig(string $extension, string $password): array
    {
        return [
            'sip_server'  => config('mikopbx.dialer.sip_server'),
            'ws_port'     => config('mikopbx.dialer.sip_ws_port', 8088),
            'wss_enabled' => config('mikopbx.dialer.sip_wss_enabled', false),
            'stun_server' => config('mikopbx.dialer.stun_server'),
            'extension'   => $extension,
            'password'    => $password,
            'display_name'=> auth()->user()?->name ?? $extension,
            'ws_url'      => sprintf(
                '%s://%s:%d/ws',
                config('mikopbx.dialer.sip_wss_enabled') ? 'wss' : 'ws',
                config('mikopbx.dialer.sip_server'),
                config('mikopbx.dialer.sip_ws_port', 8088)
            ),
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('mikopbx.dialer.enabled', true);
    }
}
