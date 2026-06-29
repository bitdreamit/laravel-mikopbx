<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Call Center') — MikoPBX</title>

    {{--
        ⚠️  DO NOT load Alpine.js from CDN here.
        Livewire v3 bundles its own Alpine internally via @livewireScripts.
        Loading a second Alpine causes "multiple instances" error and breaks
        all x-data components (tab, tasks, dashboard etc. become undefined).
    --}}

    {{-- Tailwind CDN (replace with Vite build in production) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Chart.js (safe — not Alpine) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

    {{--
        JsSIP — WebRTC SIP client for the browser dialer.
        Loaded here (before Alpine) so mikopbxApp.init() can access window.JsSIP.
        MikoPBX requires extensions to register with -WS suffix for WebRTC.
    --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jssip/3.10.0/jssip.min.js"></script>

    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link { @apply flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors; }
        .sidebar-link.active { @apply bg-indigo-50 text-indigo-700; }
        .stat-card { @apply bg-white rounded-xl shadow-sm border border-gray-100 p-5; }
        .btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors; }
        .btn-secondary { @apply inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors; }
        .btn-danger { @apply inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors; }
        .btn-success { @apply inline-flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-colors; }
        .badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
        .table-row { @apply bg-white border-b border-gray-50 hover:bg-gray-50 transition-colors; }
        .input { @apply block w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500; }
        .pulse-green { animation: pulseGreen 2s infinite; }
        @keyframes pulseGreen { 0%,100%{opacity:1;} 50%{opacity:.4;} }
    </style>

    {{--
        Register Alpine component functions BEFORE @livewireScripts boots Alpine.
        All functions placed here in a plain <script> (no defer/module) are
        available synchronously when Alpine initialises.
    --}}
    <script>
        // ── Global Alpine component functions ────────────────────────────────
        // These must live in a plain non-deferred <script> in <head> so
        // Livewire's bundled Alpine can discover them at boot time.

        function mikopbxApp() {
            return {
                // ── State ──────────────────────────────────────────────────
                sysOnline:     true,
                activeCalls:   0,
                agentsOnline:  0,
                dialerOpen:    false,
                dialString:    '',
                inCall:        false,
                callStatus:    '',   // ringing | active | failed
                callTimer:     '00:00',
                sipRegistered: false,
                quickDial:     '',
                _callSec:      0,
                _timerRef:     null,
                _ua:           null,   // JsSIP UserAgent
                _session:      null,   // JsSIP RTCSession

                init() {
                    // Poll stats every 10s
                    this.pollStats();
                    setInterval(() => this.pollStats(), 10000);

                    // Livewire event bindings
                    document.addEventListener('livewire:initialized', () => {
                        Livewire.on('play-ringtone',  () => {
                            const a = document.getElementById('mikopbx-ringtone');
                            if (a) a.play().catch(() => {});
                        });
                        Livewire.on('stop-ringtone',  () => {
                            const a = document.getElementById('mikopbx-ringtone');
                            if (a) { a.pause(); a.currentTime = 0; }
                        });
                        Livewire.on('click-to-call',  (e) => {
                            const num = Array.isArray(e) ? e[0]?.to : e?.to;
                            if (num) this.dial(num);
                        });
                        Livewire.on('toast', (e) => {
                            const data = Array.isArray(e) ? e[0] : e;
                            document.dispatchEvent(new CustomEvent('mikopbx:add-toast', { detail: data }));
                        });
                    });

                    // Allow any JS on the page to trigger a call
                    window.mikopbxDial = (num) => this.dial(String(num));

                    // Boot JsSIP after a short delay so DOM is ready
                    setTimeout(() => this.initSIP(), 500);
                },

                // ── Statistics polling ───────────────────────────────────
                async pollStats() {
                    try {
                        const r = await fetch('{{ route("mikopbx.calls.active") }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (r.ok) {
                            const d = await r.json();
                            this.activeCalls = Array.isArray(d) ? d.length : (d.data?.length ?? 0);
                            this.sysOnline   = true;
                        }
                    } catch { this.sysOnline = false; }

                    try {
                        const r = await fetch('{{ route("mikopbx.agents.statuses") }}');
                        if (r.ok) {
                            const d  = await r.json();
                            const ag = d.data ?? d;
                            this.agentsOnline = Array.isArray(ag)
                                ? ag.filter(a => ['online','busy'].includes(a.status)).length
                                : 0;
                        }
                    } catch {}
                },

                // ── JsSIP Initialisation ─────────────────────────────────
                async initSIP() {
                    if (typeof JsSIP === 'undefined') return;

                    try {
                        // Fetch SIP config for the current logged-in user
                        const r = await fetch('{{ route("mikopbx.dialer.config") }}');
                        if (!r.ok) return;
                        const cfg = await r.json();

                        if (!cfg.enabled || !cfg.extension) return;

                        /*
                         * MikoPBX WebRTC config:
                         *   ws_url:       wss://pbx.htncr.org:8089/asterisk/ws
                         *   ws_extension: 101-WS   (plain extension + "-WS" suffix)
                         *   sip_uri:      sip:101-WS@pbx.htncr.org
                         *   password:     SIP password for this extension
                         */
                        // ── Enable JsSIP debug logging to browser console ─────
                        // Open DevTools → Console to see all SIP messages
                        JsSIP.debug.enable('JsSIP:*');

                        console.log('[MikoPBX Dialer] Config received:', {
                            ws_url:       cfg.ws_url,
                            sip_uri:      cfg.sip_uri,
                            ws_extension: cfg.ws_extension,
                            sip_server:   cfg.sip_server,
                        });

                        const socket = new JsSIP.WebSocketInterface(cfg.ws_url);

                        // Log WebSocket events
                        socket.via_transport = 'WSS';

                        this._ua = new JsSIP.UA({
                            sockets:          [socket],
                            uri:              cfg.sip_uri,
                            password:         cfg.password,
                            display_name:     cfg.display_name,
                            register:         true,
                            register_expires: 300,
                            session_timers:   false,
                            pcConfig: {
                                iceServers: [{ urls: cfg.stun_server }],
                            },
                        });

                        this._ua.on('connecting', (e) => {
                            console.log('[MikoPBX Dialer] WebSocket connecting to:', cfg.ws_url);
                            this._setDialerStatus('connecting…', 'yellow');
                        });

                        this._ua.on('connected', (e) => {
                            console.log('[MikoPBX Dialer] WebSocket connected ✅');
                            this._setDialerStatus('connected, registering…', 'yellow');
                        });

                        this._ua.on('disconnected', (e) => {
                            console.error('[MikoPBX Dialer] WebSocket DISCONNECTED ❌', {
                                error: e.error,
                                code:  e.code,
                                reason:e.reason,
                            });
                            this.sipRegistered = false;
                            this._setDialerStatus('WS disconnected: ' + (e.reason || e.code), 'red');
                        });

                        this._ua.on('registered', (e) => {
                            console.log('[MikoPBX Dialer] SIP Registered ✅ as', cfg.sip_uri);
                            this.sipRegistered = true;
                            this._setDialerStatus('registered', 'green');
                        });

                        this._ua.on('unregistered', (e) => {
                            console.warn('[MikoPBX Dialer] SIP Unregistered', e);
                            this.sipRegistered = false;
                            this._setDialerStatus('unregistered', 'gray');
                        });

                        this._ua.on('registrationFailed', (e) => {
                            console.error('[MikoPBX Dialer] Registration FAILED ❌', {
                                cause:    e.cause,
                                response: e.response?.status_code,
                                reason:   e.response?.reason_phrase,
                            });
                            this.sipRegistered = false;
                            this._setDialerStatus('reg failed: ' + e.cause, 'red');
                        });

                        // Handle incoming WebRTC call
                        this._ua.on('newRTCSession', (e) => {
                            console.log('[MikoPBX Dialer] New RTC session, originator:', e.originator);
                            if (e.originator === 'remote') {
                                this._attachSession(e.session);
                                const from = e.request?.from?.uri?.user ?? 'Unknown';
                                this.dialString = from;
                                this.callStatus = 'ringing';
                                this.inCall     = true;
                                this.dialerOpen = true;
                                e.session.answer({ mediaConstraints: { audio: true, video: false } });
                            }
                        });

                        console.log('[MikoPBX Dialer] Starting JsSIP UA…');
                        this._ua.start();

                    } catch (err) {
                        console.warn('SIP init error:', err);
                    }
                },

                // ── Keypad ──────────────────────────────────────────────
                pressKey(k)  { this.dialString += k; },
                clearDial()  { this.dialString = this.dialString.slice(0, -1); },

                dial(num) {
                    if (!num) return;
                    this.dialString  = String(num);
                    this.dialerOpen  = true;
                    // Small delay so dialer panel opens before call starts
                    setTimeout(() => this.makeCall(), 150);
                },

                // ── Call control ─────────────────────────────────────────
                makeCall() {
                    if (!this.dialString) return;

                    this.callStatus = 'ringing';
                    this.inCall     = true;
                    this._callSec   = 0;
                    this.callTimer  = '00:00';
                    clearInterval(this._timerRef);

                    this._timerRef = setInterval(() => {
                        this._callSec++;
                        const m = String(Math.floor(this._callSec / 60)).padStart(2, '0');
                        const s = String(this._callSec % 60).padStart(2, '0');
                        this.callTimer = `${m}:${s}`;
                    }, 1000);

                    if (this.sipRegistered && this._ua) {
                        // ── WebRTC call via JsSIP ──────────────────────────
                        // This makes a real browser-to-browser SIP call
                        // MikoPBX connects the WebRTC call to the PSTN/extension
                        try {
                            const server = this._ua.configuration?.uri?.host ?? '';
                            const session = this._ua.call(`sip:${this.dialString}@${server}`, {
                                mediaConstraints: { audio: true, video: false },
                                rtcOfferConstraints: { offerToReceiveAudio: true, offerToReceiveVideo: false },
                            });
                            this._attachSession(session);
                        } catch (err) {
                            console.error('JsSIP call error:', err);
                            this.callStatus = 'failed';
                            this.endCall();
                        }
                    } else {
                        // ── Fallback: Click-to-call via AMI (server-side) ──
                        // When SIP not registered, we ask the server to originate
                        // the call: MikoPBX rings the agent's desk phone first,
                        // then connects to the destination when answered.
                        fetch('{{ route("mikopbx.calls.originate") }}', {
                            method:  'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                from: '{{ auth()->user()?->pbx_extension ?? "101" }}',
                                to:   this.dialString,
                            }),
                        })
                        .then(r => r.json())
                        .then(d => {
                            this.callStatus = d.success ? 'active' : 'failed';
                            if (!d.success) {
                                clearInterval(this._timerRef);
                                this.inCall = false;
                                document.dispatchEvent(new CustomEvent('mikopbx:add-toast', {
                                    detail: { type: 'error', msg: d.message ?? 'Call failed' }
                                }));
                            }
                        })
                        .catch(() => {
                            this.callStatus = 'failed';
                            this.endCall();
                        });
                    }
                },

                endCall() {
                    this._session?.terminate?.();
                    this._session = null;
                    clearInterval(this._timerRef);
                    this.inCall     = false;
                    this.callStatus = '';
                    this.callTimer  = '00:00';
                    this._callSec   = 0;
                },

                // ── Debug status helper ──────────────────────────────────
                _setDialerStatus(msg, color) {
                    // Updates a small debug line under the dialer dot
                    const el = document.getElementById('mikopbx-dialer-debug');
                    if (el) {
                        el.textContent = msg;
                        el.className   = 'text-xs mt-1 ' + {
                            green:  'text-green-300',
                            yellow: 'text-yellow-300',
                            red:    'text-red-300',
                            gray:   'text-gray-400',
                        }[color] || 'text-gray-400';
                    }
                },

                // ── JsSIP session helpers ────────────────────────────────
                _attachSession(session) {
                    this._session = session;

                    session.on('confirmed', () => {
                        this.callStatus = 'active';
                    });

                    session.on('ended', () => {
                        this.callStatus = 'ended';
                        setTimeout(() => this.endCall(), 1500);
                    });

                    session.on('failed', (e) => {
                        this.callStatus = 'failed';
                        console.warn('Call failed:', e.cause);
                        setTimeout(() => this.endCall(), 2000);
                    });

                    // Route remote audio to <audio> element
                    session.on('peerconnection', (e) => {
                        e.peerconnection.addEventListener('track', (trackEvent) => {
                            const audioEl = document.getElementById('mikopbx-remote-audio');
                            if (audioEl && trackEvent.streams[0]) {
                                audioEl.srcObject = trackEvent.streams[0];
                                audioEl.play().catch(() => {});
                            }
                        });
                    });
                },
            };
        }


        function toastManager() {
            return {
                toasts: [],
                init() {
                    document.addEventListener('mikopbx:add-toast', e => this.add(e.detail));
                },
                add({ type = 'info', msg = '' }) {
                    const id = Date.now();
                    this.toasts.push({ id, type, msg, visible: true });
                    setTimeout(() => {
                        const t = this.toasts.find(x => x.id === id);
                        if (t) t.visible = false;
                    }, 4000);
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(x => x.id !== id);
                    }, 4500);
                }
            };
        }

        function dashboard() {
            return {
                activeCalls: 0,
                init() {
                    setInterval(async () => {
                        try {
                            const r = await fetch('{{ route("mikopbx.calls.active") }}');
                            const d = await r.json();
                            this.activeCalls = Array.isArray(d) ? d.length : (d.data?.length ?? 0);
                        } catch {}
                    }, 8000);
                }
            };
        }

        function taskManager() {
            const _def = { pending: [], done: [], transferred: [] };
            let stored = _def;
            try {
                stored = JSON.parse(localStorage.getItem('mikopbx_tasks') || 'null') || _def;
            } catch {}
            return {
                tab:     'pending',
                newTask: '',
                tasks:   stored,
                save()   { try { localStorage.setItem('mikopbx_tasks', JSON.stringify(this.tasks)); } catch {} },
                addTask() {
                    if (!this.newTask.trim()) return;
                    this.tasks.pending.unshift({
                        id:   Date.now(),
                        text: this.newTask.trim(),
                        time: new Date().toLocaleTimeString()
                    });
                    this.newTask = '';
                    this.save();
                },
                completeTask(tab, i) {
                    const t = this.tasks[tab].splice(i, 1)[0];
                    if (tab !== 'done') this.tasks.done.unshift({ ...t, time: new Date().toLocaleTimeString() });
                    this.save();
                },
                transferTask(tab, i) {
                    const t = this.tasks[tab].splice(i, 1)[0];
                    this.tasks.transferred.unshift({ ...t, time: new Date().toLocaleTimeString() });
                    this.save();
                },
                removeTask(tab, i) { this.tasks[tab].splice(i, 1); this.save(); }
            };
        }

        function campaignCreate() {
            return {
                type:       'agent_connect',
                inputMode:  'file',
                numbersText:'',
                fileCount:  0,
                countFile(e) {
                    const f = e.target.files[0];
                    if (!f) return;
                    const r = new FileReader();
                    r.onload = ev => {
                        this.fileCount = ev.target.result.split('\n').filter(l => l.trim()).length;
                    };
                    r.readAsText(f);
                }
            };
        }

        function recordingPlayer() {
            return {
                currentFile:  '',
                currentLabel: '',
                playing:      false,

                /**
                 * Play a recording.
                 *
                 * @param {string} key   — either a relative playback_url
                 *                         e.g. "/pbxcore/api/v3/cdr:playback?token=abc123"
                 *                         or a legacy filename e.g. "mikopbx-xxx.webm"
                 * @param {string} label — display label for the sticky player
                 *
                 * The key is passed to our Laravel proxy route which adds Bearer auth
                 * and streams the audio from MikoPBX.
                 */
                play(key, label) {
                    const player = document.getElementById('recording-player');
                    if (!player) return;

                    // Toggle pause/resume if same file
                    if (this.currentFile === key) {
                        this.playing ? player.pause() : player.play().catch(() => {});
                        return;
                    }

                    this.currentFile  = key;
                    this.currentLabel = label;
                    this.playing      = false;

                    // Build proxy URL — Laravel route adds Bearer auth header
                    const proxyUrl = `/{{ config('mikopbx.route_prefix','pbx') }}/recordings/play?filename=${encodeURIComponent(key)}`;

                    player.src = proxyUrl;
                    player.load();
                    player.play()
                        .then(() => { this.playing = true; })
                        .catch(err => {
                            console.warn('Audio play failed:', err);
                            this.playing = false;
                        });
                }
            };
        }

        function conferenceRoom(roomId) {
            return {
                roomId,
                participants: [],
                dialIn:       '',
                async kick(channel) {
                    await fetch('{{ route("mikopbx.conference.kick") }}', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body:    JSON.stringify({ channel, room: this.roomId })
                    });
                    this.participants = this.participants.filter(p => p.channel !== channel);
                },
                async muteP(channel) {
                    await fetch('{{ route("mikopbx.conference.mute") }}', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body:    JSON.stringify({ channel, room: this.roomId })
                    });
                },
                addParticipant() {
                    if (!this.dialIn) return;
                    window.mikopbxDial && window.mikopbxDial(this.dialIn);
                    this.dialIn = '';
                }
            };
        }
    </script>
</head>
<body class="h-full flex overflow-hidden" x-data="mikopbxApp()">

{{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
<div class="w-60 flex-shrink-0 bg-white border-r border-gray-100 flex flex-col shadow-sm">

    {{-- Logo --}}
    <div class="h-16 flex items-center px-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900">MikoPBX</p>
                <p class="text-xs text-gray-400">Call Center</p>
            </div>
        </div>
    </div>

    {{-- System status --}}
    <div class="px-4 py-2 border-b border-gray-100">
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <span :class="sysOnline ? 'bg-green-400 pulse-green' : 'bg-red-400'"
                  class="w-2 h-2 rounded-full flex-shrink-0"></span>
            <span x-text="sysOnline ? 'MikoPBX Online' : 'MikoPBX Offline'"></span>
            <span class="ml-auto font-semibold text-gray-900" x-text="activeCalls + ' live'"></span>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <a href="{{ route('mikopbx.dashboard') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.dashboard') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <p class="px-3 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Calls</p>

        <a href="{{ route('mikopbx.calls.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.calls.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            Call Logs
        </a>

        <a href="{{ route('mikopbx.recordings.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.recordings.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
            </svg>
            Recordings
        </a>

        <p class="px-3 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Agents</p>

        <a href="{{ route('mikopbx.agents.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.agents.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Agents
        </a>

        <a href="{{ route('mikopbx.callbacks.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.callbacks.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Callbacks
            @php $pendingCbCount = \BitDreamIT\MikoPBX\Models\Callback::where('status','pending')->count() @endphp
            @if($pendingCbCount)
                <span class="ml-auto badge bg-red-100 text-red-700">{{ $pendingCbCount }}</span>
            @endif
        </a>

        <p class="px-3 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Campaigns</p>

        <a href="{{ route('mikopbx.campaigns.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.campaigns.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
            Campaigns
        </a>

        <a href="{{ route('mikopbx.ivr.builder') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.ivr.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            IVR Builder
        </a>

        <a href="{{ route('mikopbx.conference.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.conference.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
            </svg>
            Conference
        </a>

        <p class="px-3 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Insights</p>

        <a href="{{ route('mikopbx.analytics.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.analytics.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Analytics
        </a>

        <a href="{{ route('mikopbx.blacklist.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.blacklist.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            Blacklist
        </a>

        <a href="{{ route('mikopbx.health.index') }}"
           class="sidebar-link {{ request()->routeIs('mikopbx.health.*') ? 'active' : '' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            System Health
        </a>
    </nav>

    {{-- Dialer toggle + User --}}
    <div class="border-t border-gray-100 p-3 space-y-2">
        <button @click="dialerOpen = !dialerOpen"
                class="w-full flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Web Dialer
            <span class="ml-auto text-indigo-200 text-xs" x-text="dialerOpen ? '▲' : '▼'"></span>
        </button>
        <div class="flex items-center gap-2 px-1">
            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold">
                {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-gray-900 truncate">{{ auth()->user()?->name ?? 'Agent' }}</p>
                <p class="text-xs text-gray-400 truncate">{{ auth()->user()?->email ?? '' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- ── Main Content ─────────────────────────────────────────────────── --}}
<div class="flex-1 flex flex-col overflow-hidden">

    {{-- Top bar --}}
    <header class="h-16 bg-white border-b border-gray-100 flex items-center px-6 gap-4 flex-shrink-0">
        <h1 class="text-lg font-semibold text-gray-900">@yield('heading', 'Dashboard')</h1>
        <div class="ml-auto flex items-center gap-3">
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-green-400 rounded-full pulse-green"></span>
                    <span x-text="activeCalls"></span> active
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                    <span x-text="agentsOnline"></span> online
                </span>
            </div>
            {{-- Quick dial --}}
            <div class="relative">
                <input x-model="quickDial"
                       @keydown.enter="dial(quickDial); quickDial=''"
                       placeholder="Quick dial…"
                       class="w-36 text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
    </header>

    {{-- Flash messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show"
             class="mx-6 mt-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show"
             class="mx-6 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
            ❌ {{ session('error') }}
        </div>
    @endif

    {{-- Page content --}}
    <main class="flex-1 overflow-y-auto p-6">
        @yield('content')
    </main>
</div>

{{-- ── Web Dialer Panel ─────────────────────────────────────────────── --}}
<div x-show="dialerOpen" x-cloak
     class="fixed bottom-0 right-0 w-72 bg-white border border-gray-200 shadow-2xl rounded-tl-2xl z-50">
    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                      :class="sipRegistered ? 'bg-green-400 pulse-green' : 'bg-gray-300'"></span>
                <span class="text-sm font-semibold text-gray-800">Web Dialer</span>
                <span class="text-xs ml-1"
                      :class="sipRegistered ? 'text-green-600' : 'text-gray-400'"
                      x-text="sipRegistered ? '✓ Ready' : '○ Offline'"></span>
            </div>
            {{-- Debug status line — shows exact SIP registration state --}}
            <p id="mikopbx-dialer-debug" class="text-xs text-gray-400 ml-4 mt-0.5">
                loading config…
            </p>
        </div>
        <button @click="dialerOpen = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none flex-shrink-0">✕</button>
    </div>

    <div class="p-4">
        {{-- Display --}}
        <div class="bg-gray-900 text-white rounded-xl p-4 mb-3 min-h-14 flex items-end">
            <span class="text-2xl font-mono tracking-wider break-all" x-text="dialString || '—'"></span>
        </div>

        {{-- Status --}}
        <div x-show="callStatus"
             class="text-center text-sm mb-3 font-medium"
             :class="{
                 'text-green-600':  callStatus === 'active',
                 'text-yellow-600': callStatus === 'ringing',
                 'text-red-600':    callStatus === 'failed'
             }"
             x-text="callStatus"></div>

        {{-- Keypad --}}
        <div class="grid grid-cols-3 gap-2 mb-3">
            @foreach(['1','2','3','4','5','6','7','8','9','*','0','#'] as $k)
                <button @click="pressKey('{{ $k }}')"
                        class="h-11 bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 rounded-xl text-base font-semibold text-gray-800 transition-colors active:scale-95">
                    {{ $k }}
                </button>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="grid grid-cols-3 gap-2">
            <button @click="clearDial()"
                    class="h-10 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-600 font-medium transition-colors">
                ⌫
            </button>
            <template x-if="!inCall">
                <button @click="makeCall()"
                        class="col-span-2 h-10 bg-green-500 hover:bg-green-600 rounded-xl text-white font-bold transition-colors">
                    📞 Call
                </button>
            </template>
            <template x-if="inCall">
                <button @click="endCall()"
                        class="col-span-2 h-10 bg-red-500 hover:bg-red-600 rounded-xl text-white font-bold transition-colors">
                    End Call
                </button>
            </template>
        </div>

        {{-- Timer --}}
        <div x-show="inCall" class="text-center mt-2 text-sm text-gray-500 font-mono" x-text="callTimer"></div>
    </div>
</div>

{{-- ── Incoming Call Popup (Livewire) ──────────────────────────────── --}}
@livewire('mikopbx-incoming-popup')

{{-- ── Toast Notifications ──────────────────────────────────────────── --}}
<div x-data="toastManager()" class="fixed top-4 right-4 z-50 space-y-2 w-80 pointer-events-none">
    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             :class="{
                 'bg-green-50 border-green-200 text-green-800':  t.type === 'success',
                 'bg-red-50   border-red-200   text-red-800':    t.type === 'error',
                 'bg-blue-50  border-blue-200  text-blue-800':   t.type === 'info',
                 'bg-yellow-50 border-yellow-200 text-yellow-800': t.type === 'warning',
             }"
             class="flex items-start gap-3 p-3 border rounded-xl shadow-lg text-sm pointer-events-auto">
            <span x-text="{ success:'✅', error:'❌', info:'ℹ️', warning:'⚠️' }[t.type]"></span>
            <span x-text="t.msg" class="flex-1"></span>
        </div>
    </template>
</div>

{{-- Ringtone audio — played for incoming call alert --}}
<audio id="mikopbx-ringtone" loop preload="none" src="/vendor/mikopbx/ringtone.mp3"></audio>

{{--
    Remote audio — receives the actual voice stream from JsSIP WebRTC.
    JsSIP sets srcObject on this element when a call is connected.
    Must be in the DOM at all times (autoplay attribute required).
--}}
<audio id="mikopbx-remote-audio" autoplay playsinline></audio>

{{--
    @livewireScripts must come LAST — it boots Livewire AND Alpine.
    All Alpine component functions must already be defined before this runs.
    That is why we put all function definitions in <head> above.
--}}
@livewireScripts

@stack('scripts')
</body>
</html>
