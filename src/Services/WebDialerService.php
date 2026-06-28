<?php

namespace BitDreamIT\MikoPBX\Services;

/**
 * WebDialerService
 *
 * Builds the JsSIP configuration object that is passed to the browser.
 *
 * ── How extension is resolved ────────────────────────────────────────────────
 *
 *   Priority 1: users.pbx_extension column
 *               Set via: $user->update(['pbx_extension' => '101'])
 *               Requires migration 000002_add_pbx_fields_to_users_table
 *
 *   Priority 2: mikopbx_extensions.extension where email = user email
 *               Populated via: php artisan mikopbx:sync-extensions
 *
 *   Priority 3: null → dialer disabled for this user
 *
 * ── MikoPBX WebRTC requirements ─────────────────────────────────────────────
 *
 *   Extension suffix: MikoPBX requires "-WS" suffix for WebRTC registrations.
 *     Extension "101" → SIP URI: sip:101-WS@pbx.htncr.org
 *     This is handled automatically — you store "101", we append "-WS".
 *
 *   WebSocket URL: wss://pbx.htncr.org:8089/asterisk/ws
 *     Path must be /asterisk/ws (NOT /ws)
 *     Port is usually 8089 (WSS) or 8088 (WS)
 *
 *   In MikoPBX Admin → Network → WebRTC → Enable WebRTC must be ON.
 *   In MikoPBX Admin → Extensions → the extension must have "Use WebRTC" enabled.
 */
class WebDialerService
{
    public function isEnabled(): bool
    {
        return (bool) config('mikopbx.dialer.enabled', true);
    }

    /**
     * Get the extension number for the currently authenticated user.
     * Returns null if no extension is assigned.
     */
    public function getExtensionForUser(): ?string
    {
        $user = auth()->user();
        if (! $user) return null;

        // Priority 1: pbx_extension column on users table
        if (! empty($user->pbx_extension)) {
            return (string) $user->pbx_extension;
        }

        // Priority 2: look up by email in mikopbx_extensions table
        return \BitDreamIT\MikoPBX\Models\Extension::where('email', $user->email)
            ->value('extension');
    }

    /**
     * Get the SIP password for the currently authenticated user.
     * Stored in users.pbx_sip_password (set when creating SIP extension in MikoPBX).
     */
    public function getPasswordForUser(): string
    {
        return (string) (auth()->user()?->pbx_sip_password ?? '');
    }

    /**
     * Build the full JsSIP configuration array for the current user.
     *
     * @param string|null $extension  Override — null = auto-detect from user
     * @param string      $password   Override — empty = auto-detect from user
     *
     * @return array{
     *   enabled: bool,
     *   extension: string|null,
     *   ws_extension: string,
     *   sip_uri: string,
     *   ws_url: string,
     *   sip_server: string,
     *   password: string,
     *   display_name: string,
     *   stun_server: string
     * }
     */
    public function getConfig(?string $extension = null, string $password = ''): array
    {
        $extension = $extension ?? $this->getExtensionForUser();
        $password  = $password  ?: $this->getPasswordForUser();

        if (! $extension) {
            return [
                'enabled'   => true,
                'extension' => null,
                'error'     => 'No extension assigned to this user. ' .
                               'Set users.pbx_extension or run mikopbx:sync-extensions.',
            ];
        }

        $server = config('mikopbx.dialer.sip_server');
        $port   = (int) config('mikopbx.dialer.sip_ws_port', 8089);
        $wss    = (bool) config('mikopbx.dialer.sip_wss_enabled', false);
        $proto  = $wss ? 'wss' : 'ws';
        $stun   = config('mikopbx.dialer.stun_server', 'stun:stun.l.google.com:19302');

        // MikoPBX WebRTC: extension must have -WS suffix
        $wsExtension = $extension . '-WS';

        // WebSocket URL — path MUST be /asterisk/ws for MikoPBX
        $wsUrl = "{$proto}://{$server}:{$port}/asterisk/ws";

        // Full SIP URI for registration
        $sipUri = "sip:{$wsExtension}@{$server}";

        return [
            'enabled'      => true,
            'extension'    => $extension,      // "101"
            'ws_extension' => $wsExtension,    // "101-WS"
            'sip_uri'      => $sipUri,         // "sip:101-WS@pbx.htncr.org"
            'ws_url'       => $wsUrl,          // "wss://pbx.htncr.org:8089/asterisk/ws"
            'sip_server'   => $server,         // "pbx.htncr.org"
            'password'     => $password,
            'display_name' => auth()->user()?->name ?? $extension,
            'stun_server'  => $stun,           // "stun:stun.l.google.com:19302"
        ];
    }
}
