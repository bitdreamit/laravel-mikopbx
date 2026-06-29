{{--
    Dialer Debug Page — visit /pbx/dialer/debug to diagnose WebRTC issues.
    Shows all 5 possible failure points with live test results.
    REMOVE from production or guard with admin middleware.
--}}
@extends('mikopbx::layouts.app')
@section('title','Dialer Debug')
@section('heading','Web Dialer Debug')

@push('scripts')
{{-- Ensure JsSIP is available on the debug page even if layout load failed --}}
<script>
if (typeof JsSIP === 'undefined') {
    const s = document.createElement('script');
    s.src = '{{ asset("vendor/mikopbx/jssip.min.js") }}';
    s.onload  = () => console.log('[Debug] JsSIP loaded via fallback');
    s.onerror = () => console.error('[Debug] JsSIP fallback also failed — check public/vendor/mikopbx/jssip.min.js');
    document.head.appendChild(s);
}
</script>
@endpush

@section('content')
<div class="max-w-2xl space-y-4" x-data="dialerDebug()">

    {{-- Step 1: Config --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Step 1 — Dialer Config API</h3>
            <span :class="checks.config === true ? 'text-green-600' :
                          checks.config === false ? 'text-red-600' : 'text-gray-400'"
                  x-text="checks.config === true ? '✅ Pass' :
                           checks.config === false ? '❌ Fail' : '⏳ Pending'"></span>
        </div>
        <pre class="bg-gray-50 rounded-lg p-3 text-xs overflow-auto max-h-48"
             x-text="results.config || 'Not checked yet'"></pre>
    </div>

    {{-- Step 2: WebSocket reachability --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Step 2 — WebSocket Connection</h3>
            <span :class="checks.ws === true ? 'text-green-600' :
                          checks.ws === false ? 'text-red-600' : 'text-gray-400'"
                  x-text="checks.ws === true ? '✅ Connected' :
                           checks.ws === false ? '❌ Failed' : '⏳ Pending'"></span>
        </div>
        <p class="text-xs text-gray-500 mb-2">
            Tests raw WebSocket connection to <code x-text="wsUrl || '…'"></code>
        </p>
        <pre class="bg-gray-50 rounded-lg p-3 text-xs" x-text="results.ws || 'Not checked yet'"></pre>
        <div class="mt-3 p-3 bg-yellow-50 rounded-lg text-xs text-yellow-800">
            <strong>Common failures:</strong><br>
            • Port 8089 not open in MikoPBX VPS firewall → open UDP/TCP 8089<br>
            • SSL certificate invalid → use <code>ws://</code> (not wss://) for self-signed<br>
            • MikoPBX WebRTC not enabled → Admin → Network → WebRTC → Enable
        </div>
    </div>

    {{-- Step 3: JsSIP loaded --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Step 3 — JsSIP Library</h3>
            <span :class="checks.jssip === true ? 'text-green-600' : 'text-red-600'"
                  x-text="checks.jssip ? '✅ Loaded' : '❌ Not found'"></span>
        </div>
        <pre class="bg-gray-50 rounded-lg p-3 text-xs" x-text="results.jssip"></pre>
    </div>

    {{-- Step 4: Microphone permission --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Step 4 — Microphone Permission</h3>
            <span :class="checks.mic === true ? 'text-green-600' :
                          checks.mic === false ? 'text-red-600' : 'text-gray-400'"
                  x-text="checks.mic === true ? '✅ Granted' :
                           checks.mic === false ? '❌ Denied' : '⏳ Not tested'"></span>
        </div>
        <button @click="testMic()"
                class="btn-secondary text-xs mb-2">Request Microphone</button>
        <pre class="bg-gray-50 rounded-lg p-3 text-xs" x-text="results.mic || 'Click button to test'"></pre>
        <div class="mt-2 p-3 bg-blue-50 rounded-lg text-xs text-blue-800">
            <strong>HTTPS required</strong> — Browsers block mic access on plain HTTP.<br>
            Your site is at <code>http://htncr.com</code> — you need HTTPS for WebRTC to work.
        </div>
    </div>

    {{-- Step 5: SIP Registration --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-900">Step 5 — SIP Registration</h3>
            <span :class="checks.sip === true ? 'text-green-600' :
                          checks.sip === false ? 'text-red-600' : 'text-gray-400'"
                  x-text="checks.sip === true ? '✅ Registered' :
                           checks.sip === false ? '❌ Failed' : '⏳ Not started'"></span>
        </div>
        <button @click="testSIP()"
                :disabled="!checks.jssip"
                class="btn-primary text-xs mb-2">Test SIP Registration</button>
        <pre class="bg-gray-50 rounded-lg p-3 text-xs max-h-48 overflow-auto"
             x-text="results.sip || 'Click button to test'"></pre>
    </div>

    {{-- Run all --}}
    <button @click="runAll()"
            class="w-full btn-primary justify-center py-3">
        🔍 Run All Checks
    </button>

    {{-- Summary --}}
    <div x-show="summary" class="bg-gray-900 rounded-xl p-5">
        <h3 class="text-white font-semibold mb-3">Diagnosis Summary</h3>
        <pre class="text-green-400 text-xs leading-relaxed" x-text="summary"></pre>
    </div>
</div>

@push('scripts')
<script>
function dialerDebug() {
    return {
        checks:  { config: null, ws: null, jssip: null, mic: null, sip: null },
        results: { config: '', ws: '', jssip: '', mic: '', sip: '' },
        wsUrl:   '',
        config:  null,
        summary: '',

        async runAll() {
            this.summary = '';
            await this.checkConfig();
            await this.checkJsSIP();
            await this.checkWS();
            await this.testMic();
            this.buildSummary();
        },

        async checkConfig() {
            try {
                const r = await fetch('/{{ config("mikopbx.route_prefix","pbx") }}/dialer/config');
                const d = await r.json();
                this.config  = d;
                this.wsUrl   = d.ws_url ?? '';
                this.checks.config  = d.enabled && !!d.extension;
                this.results.config = JSON.stringify(d, null, 2);
            } catch (e) {
                this.checks.config  = false;
                this.results.config = 'Error: ' + e.message;
            }
        },

        checkJsSIP() {
            const ok = typeof JsSIP !== 'undefined';
            this.checks.jssip  = ok;
            this.results.jssip = ok
                ? 'JsSIP version: ' + (JsSIP.C?.VERSION ?? 'unknown')
                : 'window.JsSIP is undefined — CDN script failed to load';
        },

        async checkWS() {
            if (!this.wsUrl) {
                this.results.ws = 'No WS URL — run config check first';
                return;
            }
            this.results.ws = 'Connecting to ' + this.wsUrl + '…';

            return new Promise((resolve) => {
                const start = Date.now();
                try {
                    const ws = new WebSocket(this.wsUrl, ['sip']);

                    const timeout = setTimeout(() => {
                        ws.close();
                        this.checks.ws  = false;
                        this.results.ws = `❌ Timeout after 5s connecting to ${this.wsUrl}\n\nPossible causes:\n- Port 8089 blocked by firewall\n- MikoPBX WebRTC not enabled\n- Wrong port (try 8088 instead of 8089)`;
                        resolve();
                    }, 5000);

                    ws.onopen = () => {
                        clearTimeout(timeout);
                        const ms = Date.now() - start;
                        this.checks.ws  = true;
                        this.results.ws = `✅ WebSocket connected in ${ms}ms\nURL: ${this.wsUrl}`;
                        ws.close();
                        resolve();
                    };

                    ws.onerror = (e) => {
                        clearTimeout(timeout);
                        this.checks.ws  = false;
                        this.results.ws = `❌ WebSocket error connecting to ${this.wsUrl}\n\n` +
                            `If you see SSL errors: set MIKOPBX_SIP_WSS=false and port 8088\n` +
                            `If connection refused: open port 8089 in VPS firewall\n` +
                            `\nFirewall command on MikoPBX VPS:\n` +
                            `  ufw allow 8089/tcp\n  ufw allow 8089/udp`;
                        resolve();
                    };

                    ws.onclose = (e) => {
                        if (this.checks.ws === null) {
                            clearTimeout(timeout);
                            this.checks.ws  = false;
                            this.results.ws = `❌ WebSocket closed immediately\nCode: ${e.code}\nReason: ${e.reason || 'none'}\n\nCode 1006 = network error / firewall block`;
                            resolve();
                        }
                    };
                } catch (e) {
                    this.checks.ws  = false;
                    this.results.ws = 'Exception: ' + e.message;
                    resolve();
                }
            });
        },

        async testMic() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                stream.getTracks().forEach(t => t.stop());
                this.checks.mic  = true;
                this.results.mic = '✅ Microphone access granted\n\nNote: Mic access requires HTTPS in production.\nhttp:// only works on localhost.';
            } catch (e) {
                this.checks.mic  = false;
                this.results.mic = `❌ Mic denied: ${e.name}\n\n${e.message}\n\n` +
                    (location.protocol === 'http:'
                        ? 'Your site is HTTP — browsers block mic on HTTP.\nYou MUST use HTTPS for WebRTC calls.'
                        : 'Check browser permissions (lock icon in address bar)');
            }
        },

        testSIP() {
            if (!this.config || !this.checks.jssip) {
                this.results.sip = 'Run config check and verify JsSIP is loaded first';
                return;
            }

            const cfg = this.config;
            this.results.sip = `Attempting SIP registration:\n  URI: ${cfg.sip_uri}\n  WS:  ${cfg.ws_url}\n\n`;
            const log = (msg) => { this.results.sip += msg + '\n'; };

            JsSIP.debug.enable('JsSIP:*');

            const socket = new JsSIP.WebSocketInterface(cfg.ws_url);
            const ua = new JsSIP.UA({
                sockets:          [socket],
                uri:              cfg.sip_uri,
                password:         cfg.password,
                register:         true,
                register_expires: 30,
                session_timers:   false,
            });

            ua.on('connecting',        () => log('→ WebSocket connecting…'));
            ua.on('connected',         () => log('→ WebSocket connected ✅'));
            ua.on('disconnected',  (e) => {
                log(`→ WebSocket disconnected ❌ code=${e.code} reason=${e.reason}`);
                this.checks.sip = false;
            });
            ua.on('registered',        () => {
                log('→ SIP REGISTERED ✅');
                this.checks.sip = true;
                ua.stop();
            });
            ua.on('unregistered',  (e) => log('→ Unregistered: ' + JSON.stringify(e)));
            ua.on('registrationFailed', (e) => {
                log(`→ Registration FAILED ❌\n  cause: ${e.cause}\n  status: ${e.response?.status_code} ${e.response?.reason_phrase}`);
                this.checks.sip = false;

                // Diagnose common errors
                if (e.response?.status_code === 401 || e.response?.status_code === 403) {
                    log('\n🔑 Auth error — wrong SIP password or extension number');
                    log('   Check: MikoPBX Admin → Extensions → edit → SIP password');
                    log('   Note: The SIP password is NOT the user login password');
                    log('   Note: MikoPBX WebRTC needs "Use WebRTC" enabled on the extension');
                }
                if (e.cause === 'Connection Error') {
                    log('\n🌐 Cannot reach WebSocket — check firewall and port 8089');
                }
                if (e.cause === 'Request Timeout') {
                    log('\n⏱ MikoPBX not responding to REGISTER — check AMI/ARI is running');
                }
                ua.stop();
            });

            ua.start();

            // Auto-stop after 15s
            setTimeout(() => {
                try { ua.stop(); } catch {}
                if (this.checks.sip === null) {
                    this.results.sip += '\nTimeout — no response in 15s';
                    this.checks.sip = false;
                }
            }, 15000);
        },

        buildSummary() {
            const lines = ['=== MikoPBX Dialer Diagnosis ===', ''];
            const icon = (b) => b === true ? '✅' : b === false ? '❌' : '⏳';

            lines.push(`${icon(this.checks.config)} Config API     → ${this.checks.config ? 'OK' : 'FAIL'}`);
            lines.push(`${icon(this.checks.jssip)}  JsSIP Library  → ${this.checks.jssip ? 'Loaded' : 'Missing'}`);
            lines.push(`${icon(this.checks.ws)}  WebSocket      → ${this.checks.ws ? 'Reachable' : 'BLOCKED'}`);
            lines.push(`${icon(this.checks.mic)}  Microphone     → ${this.checks.mic ? 'Granted' : 'Denied'}`);
            lines.push('');

            if (this.checks.ws === false) {
                lines.push('🔧 FIX FIRST: WebSocket is blocked.');
                lines.push('   1. Open port 8089 on MikoPBX VPS:');
                lines.push('      ufw allow 8089/tcp && ufw allow 8089/udp');
                lines.push('   2. Or try WS (not WSS): set MIKOPBX_SIP_WSS=false, MIKOPBX_SIP_WS_PORT=8088');
                lines.push('   3. Verify MikoPBX WebRTC: Admin → Network → WebRTC → Enable');
            }

            if (this.checks.mic === false && location.protocol === 'http:') {
                lines.push('🔒 FIX: Install SSL certificate on htncr.com');
                lines.push('   WebRTC requires HTTPS for microphone access.');
                lines.push('   Free option: Certbot + Let\'s Encrypt');
                lines.push('   Until then: calls work via AMI click-to-call (no mic needed)');
            }

            if (this.checks.config === false) {
                lines.push('🔧 FIX: Assign extension to user:');
                lines.push('   DB: UPDATE users SET pbx_extension="121", pbx_sip_password="xxx"');
                lines.push('       WHERE id = ' + ({{ auth()->id() ?? 1 }}));
            }

            this.summary = lines.join('\n');
        }
    };
}
</script>
@endpush
@endsection
