{{--
    Dialer Debug Page — visit /pbx/dialer/debug to diagnose WebRTC issues.
    REMOVE from production or guard with admin middleware.
--}}
@extends('mikopbx::layouts.app')
@section('title','Dialer Debug')
@section('heading','Web Dialer Debug')

@push('scripts')
    <script>
        if (typeof JsSIP === 'undefined') {
            const s = document.createElement('script');
            s.src = '{{ asset("vendor/mikopbx/jssip.min.js") }}';
            s.onload  = () => console.log('[Debug] JsSIP loaded via fallback');
            s.onerror = () => console.error('[Debug] JsSIP fallback also failed');
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

        {{-- Step 2: WebSocket --}}
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
        </div>

        {{-- Step 3: JsSIP --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Step 3 — JsSIP Library</h3>
                <span :class="checks.jssip === true ? 'text-green-600' : 'text-red-600'"
                      x-text="checks.jssip ? '✅ Loaded' : '❌ Not found'"></span>
            </div>
            <pre class="bg-gray-50 rounded-lg p-3 text-xs" x-text="results.jssip"></pre>
        </div>

        {{-- Step 4: Microphone --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Step 4 — Microphone Permission</h3>
                <span :class="checks.mic === true ? 'text-green-600' :
                          checks.mic === false ? 'text-red-600' : 'text-gray-400'"
                      x-text="checks.mic === true ? '✅ Granted' :
                           checks.mic === false ? '❌ Denied' : '⏳ Not tested'"></span>
            </div>
            <button @click="testMic()" class="btn-secondary text-xs mb-2">Request Microphone</button>
            <pre class="bg-gray-50 rounded-lg p-3 text-xs" x-text="results.mic || 'Click button to test'"></pre>
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
            <button @click="testSIP()" :disabled="!checks.jssip" class="btn-primary text-xs mb-2">
                Test SIP Registration
            </button>
            <pre class="bg-gray-50 rounded-lg p-3 text-xs max-h-48 overflow-auto"
                 x-text="results.sip || 'Click button to test'"></pre>
        </div>

        {{-- Step 6: Test Call --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Step 6 — Test Call (via registered UA)</h3>
                <span :class="checks.call === true ? 'text-green-600' :
                          checks.call === false ? 'text-red-600' : 'text-gray-400'"
                      x-text="checks.call === true ? '✅ Calling' :
                           checks.call === false ? '❌ Failed' : '⏳ Not tested'"></span>
            </div>
            <div class="flex gap-2 mb-3">
                <input x-model="testNumber"
                       placeholder="Extension or number e.g. 101 or 01711000000"
                       class="input flex-1 text-sm">
                <button @click="testCall()"
                        :disabled="!checks.jssip || !testNumber"
                        class="btn-primary text-xs whitespace-nowrap">
                    📞 Make Test Call
                </button>
                <button @click="endTestCall()"
                        x-show="checks.call === true"
                        class="btn-danger text-xs whitespace-nowrap">
                    ✕ End
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-2">
                Requires Step 5 to pass first. Calls via WebRTC — you will hear audio in your browser.
            </p>
            <pre class="bg-gray-50 rounded-lg p-3 text-xs max-h-48 overflow-auto"
                 x-text="results.call || 'Enter a number and click Make Test Call'"></pre>
        </div>

        {{-- Run all --}}
        <button @click="runAll()" class="w-full btn-primary justify-center py-3">
            🔍 Run All Checks (Steps 1–5)
        </button>

        {{-- Summary --}}
        <div x-show="summary" class="bg-gray-900 rounded-xl p-5">
            <h3 class="text-white font-semibold mb-3">Diagnosis Summary</h3>
            <pre class="text-green-400 text-xs leading-relaxed" x-text="summary"></pre>
        </div>
    </div>

    @push('scripts')
        <script>
            // JsSIP UA/Session stored OUTSIDE Alpine proxy to avoid read-only property errors
            window._dbgUA      = null;
            window._dbgSession = null;

            function dialerDebug() {
                return {
                    checks:     { config: null, ws: null, jssip: null, mic: null, sip: null, call: null },
                    results:    { config: '', ws: '', jssip: '', mic: '', sip: '', call: '' },
                    wsUrl:      '',
                    config:     null,
                    summary:    '',
                    testNumber: '',

                    async runAll() {
                        this.summary = '';
                        await this.checkConfig();
                        this.checkJsSIP();
                        await this.checkWS();
                        await this.testMic();
                        this.buildSummary();
                    },

                    async checkConfig() {
                        try {
                            const r = await fetch('/{{ config("mikopbx.route_prefix","pbx") }}/dialer/config');
                            const d = await r.json();
                            this.config             = d;
                            this.wsUrl              = d.ws_url ?? '';
                            this.checks.config      = d.enabled && !!d.extension;
                            this.results.config     = JSON.stringify(d, null, 2);
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
                        if (!this.wsUrl) { this.results.ws = 'No WS URL — run config first'; return; }
                        this.results.ws = 'Connecting to ' + this.wsUrl + '…';
                        return new Promise((resolve) => {
                            const start = Date.now();
                            try {
                                const ws = new WebSocket(this.wsUrl, ['sip']);
                                const timeout = setTimeout(() => {
                                    ws.close();
                                    this.checks.ws  = false;
                                    this.results.ws = `❌ Timeout after 5s\n- Port 8089 blocked?\n- MikoPBX WebRTC enabled?`;
                                    resolve();
                                }, 5000);
                                ws.onopen = () => {
                                    clearTimeout(timeout);
                                    this.checks.ws  = true;
                                    this.results.ws = `✅ Connected in ${Date.now()-start}ms\nURL: ${this.wsUrl}`;
                                    ws.close(); resolve();
                                };
                                ws.onerror = () => {
                                    clearTimeout(timeout);
                                    this.checks.ws  = false;
                                    this.results.ws = `❌ WebSocket error\n- Check port 8089 firewall\n- Try ws:// port 8088 if SSL issue`;
                                    resolve();
                                };
                                ws.onclose = (e) => {
                                    if (this.checks.ws === null) {
                                        clearTimeout(timeout);
                                        this.checks.ws  = false;
                                        this.results.ws = `❌ Closed immediately\nCode: ${e.code} (1006=firewall block)`;
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
                            this.results.mic = '✅ Microphone access granted';
                        } catch (e) {
                            this.checks.mic  = false;
                            this.results.mic = `❌ Mic denied: ${e.name}\n${e.message}\n` +
                                (location.protocol === 'http:' ? '\nSite is HTTP — HTTPS required for mic access.' : '');
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

                        const socket = new JsSIP.WebSocketInterface(cfg.ws_url);
                        const ua = new JsSIP.UA({
                            sockets:            [socket],
                            uri:                cfg.sip_uri,
                            authorization_user: cfg.extension,
                            password:           cfg.password,
                            display_name:       cfg.display_name ?? cfg.extension,
                            register:           true,
                            register_expires:   30,
                            session_timers:     false,
                        });

                        ua.on('connecting',       () => log('→ WebSocket connecting…'));
                        ua.on('connected',        () => log('→ WebSocket connected ✅'));
                        ua.on('disconnected', (e) => log(`→ WebSocket disconnected ❌ code=${e.code} reason=${e.reason}`));
                        ua.on('registered',       () => {
                            log('→ SIP REGISTERED ✅');
                            this.checks.sip = true;
                            ua.stop();
                        });
                        ua.on('unregistered',     () => log('→ Unregistered'));
                        ua.on('registrationFailed', (e) => {
                            log(`→ Registration FAILED ❌\n  cause: ${e.cause}\n  status: ${e.response?.status_code} ${e.response?.reason_phrase}`);
                            this.checks.sip = false;
                            if (e.response?.status_code === 401 || e.response?.status_code === 403) {
                                log('\n🔑 Auth error — check SIP password in MikoPBX Admin → Extensions');
                            }
                            ua.stop();
                        });
                        ua.start();

                        setTimeout(() => {
                            try { ua.stop(); } catch {}
                            if (this.checks.sip === null) {
                                this.results.sip += '\nTimeout — no response in 15s';
                                this.checks.sip = false;
                            }
                        }, 15000);
                    },

                    testCall() {
                        if (!this.config || !this.checks.jssip) {
                            this.results.call = 'Run Steps 1–5 first';
                            return;
                        }
                        if (!this.testNumber) {
                            this.results.call = 'Enter a number to call';
                            return;
                        }

                        const cfg = this.config;
                        const log = (msg) => { this.results.call += msg + '\n'; };

                        this.results.call = `Calling: sip:${this.testNumber}@${cfg.sip_server}\n`;
                        this.checks.call  = null;

                        // Stop previous UA
                        try { window._dbgUA?.stop(); } catch {}
                        window._dbgUA      = null;
                        window._dbgSession = null;

                        const socket = new JsSIP.WebSocketInterface(cfg.ws_url);
                        window._dbgUA = new JsSIP.UA({
                            sockets:            [socket],
                            uri:                cfg.sip_uri,
                            authorization_user: cfg.extension,
                            password:           cfg.password,
                            display_name:       cfg.display_name ?? cfg.extension,
                            register:           true,
                            register_expires:   60,
                            session_timers:     false,
                        });

                        window._dbgUA.on('connected',  () => log('→ WS connected'));
                        window._dbgUA.on('registered', () => {
                            log('→ Registered ✅ — making call…');
                            try {
                                const target = `sip:${this.testNumber}@${cfg.sip_server}`;
                                window._dbgSession = window._dbgUA.call(target, {
                                    mediaConstraints:    { audio: true, video: false },
                                    rtcOfferConstraints: { offerToReceiveAudio: true, offerToReceiveVideo: false },
                                    pcConfig: { iceServers: [{ urls: cfg.stun_server || 'stun:stun.l.google.com:19302' }] },
                                });

                                window._dbgSession.on('progress',  (e) => log('→ Ringing… ' + (e.response?.reason_phrase ?? '')));
                                window._dbgSession.on('confirmed', ()  => {
                                    log('→ Call ANSWERED ✅ — audio playing');
                                    this.checks.call = true;
                                });
                                window._dbgSession.on('failed', (e) => {
                                    log(`→ Call FAILED ❌\n  cause: ${e.cause}\n  status: ${e.message?.status_code} ${e.message?.reason_phrase}`);
                                    this.checks.call = false;
                                });
                                window._dbgSession.on('ended', (e) => {
                                    log('→ Call ended: ' + (e.cause ?? 'Normal'));
                                    this.checks.call = false;
                                });

                                setTimeout(() => {
                                    try { window._dbgSession?.terminate(); window._dbgUA?.stop(); } catch {}
                                    if (this.checks.call === null) {
                                        log('\n⏱ No answer in 30s');
                                        this.checks.call = false;
                                    }
                                }, 30000);

                            } catch (err) {
                                log('→ Call exception: ' + err.message);
                                this.checks.call = false;
                            }
                        });

                        window._dbgUA.on('registrationFailed', (e) => {
                            log(`→ Registration failed: ${e.cause}`);
                            this.checks.call = false;
                        });
                        window._dbgUA.on('disconnected', (e) => {
                            if (this.checks.call === null) {
                                log(`→ WS disconnected: code=${e.code}`);
                                this.checks.call = false;
                            }
                        });

                        window._dbgUA.start();
                    },

                    endTestCall() {
                        try { window._dbgSession?.terminate(); } catch {}
                        try { window._dbgUA?.stop(); } catch {}
                        window._dbgSession = null;
                        window._dbgUA      = null;
                        this.checks.call   = null;
                        this.results.call += '\n→ Call ended by user';
                    },

                    buildSummary() {
                        const lines = ['=== MikoPBX Dialer Diagnosis ===', ''];
                        const icon = (b) => b === true ? '✅' : b === false ? '❌' : '⏳';
                        lines.push(`${icon(this.checks.config)} Config API    → ${this.checks.config ? 'OK' : 'FAIL'}`);
                        lines.push(`${icon(this.checks.jssip)} JsSIP Library → ${this.checks.jssip ? 'Loaded' : 'Missing'}`);
                        lines.push(`${icon(this.checks.ws)} WebSocket     → ${this.checks.ws ? 'Reachable' : 'BLOCKED'}`);
                        lines.push(`${icon(this.checks.mic)} Microphone    → ${this.checks.mic ? 'Granted' : 'Denied'}`);
                        lines.push('');
                        if (!this.checks.ws) {
                            lines.push('🔧 Open port 8089: ufw allow 8089/tcp && ufw allow 8089/udp');
                        }
                        if (!this.checks.mic && location.protocol === 'http:') {
                            lines.push('🔒 HTTPS required for microphone — install SSL certificate');
                        }
                        this.summary = lines.join('\n');
                    }
                };
            }
        </script>
    @endpush
@endsection