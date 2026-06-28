<?php

namespace BitDreamIT\MikoPBX\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Services\WebDialerService;

class WebDialerController extends Controller
{
    public function __construct(private WebDialerService $svc) {}

    /**
     * Returns the SIP/JsSIP config for the current user's extension.
     *
     * Called by the layout's Alpine mikopbxApp.init() on page load.
     *
     * How extension is found:
     *   1. users.pbx_extension column (requires migration 000002)
     *   2. mikopbx_extensions.extension where email = user email (fallback)
     *   3. ?extension= query param (manual override for testing)
     *
     * Response JSON:
     * {
     *   "enabled":      true,
     *   "extension":    "101",        // plain number
     *   "ws_extension": "101-WS",    // MikoPBX WebRTC requires -WS suffix
     *   "sip_uri":      "sip:101-WS@pbx.htncr.org",
     *   "ws_url":       "wss://pbx.htncr.org:8089/asterisk/ws",
     *   "sip_server":   "pbx.htncr.org",
     *   "password":     "sip-password",
     *   "display_name": "John Smith",
     *   "stun_server":  "stun:stun.l.google.com:19302"
     * }
     */
    public function config(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! $this->svc->isEnabled()) {
            return response()->json(['enabled' => false]);
        }

        $extension = $request->input('extension');
        $password  = $request->input('password', '');

        $config = $this->svc->getConfig($extension, $password);

        return response()->json($config);
    }
}
