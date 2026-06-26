# Changelog

All notable changes to `bitdreamit/laravel-mikopbx` will be documented in this file.

## [1.0.0] — 2026-06-24

### Added — Initial Release 🎉

#### Core Infrastructure
- `MikoPBXServiceProvider` — auto-discovers via Laravel's package discovery
- `MikoPBXManager` — central facade target with 14 named service accessors
- `MikoPBX` facade with full PHPDoc for IDE autocomplete
- `MikoPBXException` for typed error handling
- Single migration creating 10 prefixed tables (`mikopbx_*`)

#### Services (14)
- **RestApiService** — 30+ MikoPBX REST v3 endpoints (calls, extensions, CDR, campaigns, IVR, conference, system)
- **AMIService** — raw TCP socket AMI with connect/listen/originate/redirect/hangup/mute/queue
- **ARIService** — ARI REST + WebSocket URL builder
- **CampaignService** — create, start, pause, stop, syncProgress, parseNumbersFromFile, getStats
- **AgentService** — all(), sync(), setStatus(), getOnlineCount(), getAvailableAgents()
- **AnalyticsService** — summary, dailyTrend, peakHours, agentPerformance, callsByStatus
- **BlacklistService** — add, remove, isBlocked (with expiry), all
- **CallbackService** — schedule, scheduleFromMissedCall, attempt, pending
- **RecordingService** — list, proxyStream, getSignedUrl
- **ConferenceService** — getRooms, kickParticipant, muteParticipant
- **IVRService** — getMenus, save, buildTree
- **HealthCheckService** — check (AMI+ARI+SIP), latest, history
- **SmsService** — SSL Wireless + Twilio drivers, feature-gated
- **WebDialerService** — SIP.js config builder, ws:// URL generator

#### Eloquent Models (7)
- `CallLog` — with scopes (inbound/outbound/answered/missed/today), `duration_formatted` accessor
- `Campaign` — `progress`, `isRunning()`, `isDone()`, `status_badge` accessors
- `CampaignNumber` — tracks per-number dialing status and DTMF responses
- `Extension` — `is_online`, `status_color`, `status_dot` accessors
- `Blacklist` — expiry-aware
- `Callback` — priority badge, scheduled/attempted/completed timestamps
- `HealthLog` — stores AMI/ARI/SIP status per check

#### HTTP Controllers (12)
- Dashboard, Call, Campaign, Agent, Analytics, Recording, Blacklist, Callback, Conference, IVR, Health, WebDialer

#### Livewire v3 Components (10)
- `LiveCallBoard` — polls activeCalls, transfer modal, hangup, real-time Echo
- `AgentStatusGrid` — agent dots, click-to-call, Echo listener
- `CampaignManager` — start/pause/stop, live progress, polls every 8s
- `CallLogTable` — paginated with search/filter/date, Echo listener for live updates
- `BlacklistManager` — add/remove with search
- `PendingCallbacks` — attempt, cancel, priority-sorted, polls every 15s
- `IncomingCallPopup` — answer/reject/log, ringtone events, Echo-driven
- `AnalyticsDashboard` — Chart.js daily trend + peak hours + status doughnut + agent table
- `HealthMonitor` — run check, status banner, config summary, polls every 60s
- `IVRBuilderComponent` — visual node/key builder, preview pane, save to MikoPBX

#### Blade Views (19)
- `layouts/app.blade.php` — full sidebar, web dialer panel, quick-dial, agent status, toast system
- All 14 pages + 5 Livewire blade partials

#### Artisan Commands (6)
- `mikopbx:install` — publish, migrate, Supervisor config, .env example
- `mikopbx:listen` — AMI daemon with event dispatch (Newchannel/Bridge/Hangup/PeerStatus)
- `mikopbx:cdr-sync --days=N` — pull CDR from MikoPBX REST API
- `mikopbx:sync-extensions` — upsert extensions from MikoPBX
- `mikopbx:campaign-run [--sync]` — start scheduled / sync running campaigns
- `mikopbx:health` — run health check, exit code 1 on critical

#### Events (4, all ShouldBroadcast)
- `IncomingCallEvent` → channel `mikopbx.calls`, event `.incoming`
- `CallAnsweredEvent` → channel `mikopbx.calls`, event `.answered`
- `CallEndedEvent` → channel `mikopbx.calls`, event `.ended`
- `AgentStatusChangedEvent` → channel `mikopbx.agents`, event `.status`

#### Jobs & Listeners
- `ProcessCallbackJob` — schedules callback + optional SMS on missed call
- `MissedCallListener` — handles `CallEndedEvent` for missed status

#### Enums (3)
- `CallStatus` — with `label()`, `color()`, `badgeClass()`
- `CampaignStatus` — with `isActive()`, `canStart()`, `badgeClass()`
- `AgentStatus` — with `dot()`, `isAvailable()`

#### Traits
- `HasMikoPBXExtension` — add to User model for `callNumber()`, `callLogs()`, `pendingCallbacks()`

#### Testing
- `MikoPBXFake` — full test double replacing `MikoPBXManager` in service container
- Assertions: `assertOriginated`, `assertNotOriginated`, `assertOriginateCount`, `assertTransferred`, `assertHungUp`, `assertNothingOriginated`, `assertCampaignStarted`
- `FakeRestApiService` — all API calls stubbed, no HTTP made during tests
- 20+ Feature tests + 25+ Unit tests using Pest v3

#### Frontend Assets
- `resources/js/mikopbx/app.js` — ringtone management, Echo init, global `window.mikopbxDial()`
- `resources/js/mikopbx/echo-listeners.js` — channel subscriptions, browser notifications
- `resources/js/mikopbx/click-to-call.js` — Alpine.js component + `[data-pbx-call]` auto-wiring
- `resources/css/mikopbx.css` — pulse animation, waveform bars, status dots, progress shimmer

#### Routes
- `routes/web.php` — 25 named routes under configurable prefix (default: `/pbx`)
- `routes/api.php` — JSON API routes under `/api/pbx`
- `routes/webhook.php` — unauthenticated webhook receiver for MikoPBX events

---

*Built by [BitDream IT](https://bitdreamit.com), Bangladesh — the complete open-source Laravel call center package.*
