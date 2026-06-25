# bitdreamit/laravel-mikopbx

> The most complete open-source Laravel package for MikoPBX — a full call center CRM platform with auto dialer, live agent panel, web softphone, IVR builder, analytics, recordings, blacklist, callbacks, conference rooms, and system health monitoring.

[![License: MIT](https://img.shields.io/badge/License-MIT-indigo.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%2F12-red.svg)](https://laravel.com)
[![MikoPBX](https://img.shields.io/badge/MikoPBX-REST%20API%20v3-orange.svg)](https://mikopbx.com)

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [MikoPBX Setup](#mikopbx-setup)
- [Environment Variables](#environment-variables)
- [Start AMI Listener](#start-ami-listener-supervisor)
- [Pages & Routes](#pages--routes)
- [Facade Usage](#facade-usage)
- [Livewire Components](#livewire-components)
- [Artisan Commands](#artisan-commands)
- [REST API v3 Endpoints Reference](#rest-api-v3-endpoints-reference)
- [How Call Control Works](#how-call-control-works-ami-not-rest)
- [CDR Field Names](#cdr-field-names)
- [Testing with MikoPBXFake](#testing-with-mikopbxfake)
- [Database Tables](#database-tables)
- [Real-time Events](#real-time-events-laravel-echo)
- [Package Structure](#package-structure)
- [Publishing to GitHub](#publishing-to-github--packagist)
- [Troubleshooting](#troubleshooting)

---

## Features

| Feature | Description |
|---|---|
| 📞 Live Call Board | Real-time active calls with transfer, mute, hangup via AMI |
| 👥 Agent Management | Status grid, click-to-call, sync from MikoPBX, DND/away support |
| 📢 Auto Dialer | Create campaigns, upload number lists, voice broadcast, IVR survey |
| 🌿 IVR Builder | Visual node editor — Press 1 for Sales, Press 2 for Support |
| 📊 Analytics | Daily trend, peak hours, ASR %, agent performance, Chart.js |
| 🎙️ Recordings | Audio player, proxy stream, download, search by number/date |
| 🚫 Blacklist | Block inbound/outbound numbers with expiry |
| 📅 Callbacks | Schedule, prioritise, attempt, assign to agent |
| 🎙️ Conference | Room list, kick/mute participants |
| ❤️ Health Monitor | AMI + SIP + REST API status check with 60-second auto-poll |
| 📱 Web Dialer | Browser SIP softphone via SIP.js WebRTC |
| 🔔 Real-time | Laravel Echo (Reverb/Pusher) — incoming call popup, agent dots |
| 🧪 MikoPBXFake | Full test double — assertOriginated, assertTransferred etc. |

---

## Requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.2+ | Required for enums |
| Laravel | 11 or 12 | Tested on both |
| Livewire | 3.x | For real-time components |
| MikoPBX | 2024.2+ | REST API v3 must be enabled |
| Laravel Reverb or Pusher | any | For real-time Echo events |
| MySQL / PostgreSQL / SQLite | any | For local CDR storage |

---

## Installation

### Step 1 — Install the package

```bash
composer require bitdreamit/laravel-mikopbx
```

### Step 2 — Run the installer

```bash
php artisan mikopbx:install
```

This command:
- Publishes `config/mikopbx.php`
- Runs database migrations (10 tables, all prefixed `mikopbx_`)
- Writes `docs/supervisor-mikopbx-ami.conf`
- Writes `.env.mikopbx.example` with all required variables

### Step 3 — Add to .env

Copy `.env.mikopbx.example` and add the values to your `.env` file. See [Environment Variables](#environment-variables) below.

### Step 4 — Set up MikoPBX admin panel

See [MikoPBX Setup](#mikopbx-setup) below.

### Step 5 — Start the AMI listener

```bash
sudo cp docs/supervisor-mikopbx-ami.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mikopbx-ami
sudo supervisorctl status mikopbx-ami
```

### Step 6 — Sync extensions

```bash
php artisan mikopbx:sync-extensions
```

### Step 7 — Open the dashboard

Visit `https://yourapp.com/pbx` in your browser.

---

## MikoPBX Setup

### 1. Enable AMI User

Go to: **MikoPBX Admin Panel → System → AMI Users → Add**

| Field | Value |
|---|---|
| Username | `laravelapp` |
| Secret | `your-strong-ami-secret` |
| Allowed IP | Your Laravel server IP |
| Permissions | all (or: call, originate, reporting, system) |

Save and Apply Config.

### 2. Get REST API Key

Go to: **MikoPBX Admin Panel → Settings → API Keys → Generate**

Copy the JWT token and set it as `MIKOPBX_API_KEY` in your `.env`.

> The REST API v3 uses **Bearer token** authentication in the `Authorization` header.
> The old `X-Auth-Token` header is not used in v3.

### 3. Optional — Create ARI User

Go to: **MikoPBX Admin Panel → System → ARI Users → Add**

Needed only if you use the `ARIService` for WebSocket channel control.

---

## Environment Variables

```env
# ─── MikoPBX REST API v3 ───────────────────────────────────────────────────
# Base URL of your MikoPBX server (no trailing slash)
MIKOPBX_URL=https://163.223.240.124

# JWT Bearer token from MikoPBX Admin → Settings → API Keys
MIKOPBX_API_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# HTTP timeout in seconds (default 10)
MIKOPBX_TIMEOUT=10

# Set false for self-signed SSL certificates (common in local MikoPBX installs)
MIKOPBX_VERIFY_SSL=false

# ─── AMI (Asterisk Manager Interface) ─────────────────────────────────────
# AMI is used for: originate, transfer, hangup, mute, live events
# REST API v3 does NOT have call control endpoints
MIKOPBX_AMI_HOST=163.223.240.124
MIKOPBX_AMI_PORT=5038
MIKOPBX_AMI_USER=laravelapp
MIKOPBX_AMI_SECRET=your-strong-ami-secret
MIKOPBX_AMI_TIMEOUT=10

# ─── ARI (Asterisk REST Interface) ─────────────────────────────────────────
# Optional — only needed for ARIService / WebSocket channel control
MIKOPBX_ARI_URL=http://163.223.240.124:8088
MIKOPBX_ARI_USER=admin
MIKOPBX_ARI_PASSWORD=your-ari-password
MIKOPBX_ARI_APP=laravel-mikopbx

# ─── Web Dialer (SIP.js browser softphone) ─────────────────────────────────
MIKOPBX_DIALER_ENABLED=true
MIKOPBX_SIP_SERVER=163.223.240.124
MIKOPBX_SIP_WS_PORT=8088
MIKOPBX_SIP_WSS=false
MIKOPBX_STUN=stun:stun.l.google.com:19302

# ─── SMS Alerts (optional — for missed call notifications) ─────────────────
MIKOPBX_SMS_ENABLED=false
MIKOPBX_SMS_DRIVER=ssl_wireless
MIKOPBX_SMS_API_KEY=
MIKOPBX_SMS_FROM=YourSenderID

# ─── Routing ────────────────────────────────────────────────────────────────
# URL prefix for all package routes (default: pbx → /pbx/*)
MIKOPBX_ROUTE_PREFIX=pbx
```

All config values are documented in `config/mikopbx.php`.

---

## Start AMI Listener (Supervisor)

The AMI listener is the daemon that connects to MikoPBX port 5038, receives real-time events (incoming calls, hangups, agent status changes), and dispatches Laravel events for the UI.

```bash
# Copy the config generated by mikopbx:install
sudo cp docs/supervisor-mikopbx-ami.conf /etc/supervisor/conf.d/

# Load and start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mikopbx-ami

# Check status
sudo supervisorctl status mikopbx-ami
# Should show: mikopbx-ami   RUNNING   pid 12345, uptime 0:01:00

# View logs
tail -f storage/logs/mikopbx-ami.log
```

The supervisor config runs:
```
php artisan mikopbx:listen
```

This command connects to AMI, subscribes to all events, and loops forever. It auto-restarts on crash.

---

## Pages & Routes

All routes are under the configurable prefix (default `/pbx`).

| URL | Route Name | Description |
|---|---|---|
| `/pbx` | `mikopbx.dashboard` | Dashboard — live calls, task manager, campaigns, follow-up list |
| `/pbx/calls` | `mikopbx.calls.index` | CDR table with real-time filters (Livewire) |
| `/pbx/calls/{id}` | `mikopbx.calls.show` | Single call detail + recording player + actions |
| `/pbx/campaigns` | `mikopbx.campaigns.index` | Campaign cards grid |
| `/pbx/campaigns/create` | `mikopbx.campaigns.create` | Create campaign with number upload |
| `/pbx/campaigns/{id}` | `mikopbx.campaigns.show` | Live campaign detail with number list |
| `/pbx/agents` | `mikopbx.agents.index` | Agent table with status change and click-to-call |
| `/pbx/analytics` | `mikopbx.analytics.index` | Chart.js analytics dashboard |
| `/pbx/recordings` | `mikopbx.recordings.index` | Recordings with sticky audio player |
| `/pbx/blacklist` | `mikopbx.blacklist.index` | Blacklist manager |
| `/pbx/callbacks` | `mikopbx.callbacks.index` | Callback scheduler |
| `/pbx/conference` | `mikopbx.conference.index` | Conference rooms |
| `/pbx/ivr/builder` | `mikopbx.ivr.builder` | Visual IVR builder |
| `/pbx/health` | `mikopbx.health.index` | System health monitor |

### Internal API routes (JSON)

| URL | Description |
|---|---|
| `GET /api/pbx/active-calls` | Active calls list |
| `POST /api/pbx/originate` | Make a call (via AMI) |
| `POST /api/pbx/transfer` | Transfer call (via AMI) |
| `POST /api/pbx/hangup` | Hangup call (via AMI) |
| `POST /api/pbx/mute` | Mute/unmute (via AMI) |
| `GET /api/pbx/agent-statuses` | Agent status list |
| `GET /api/pbx/analytics` | Analytics data (JSON) |

### Webhook route (no auth)

| URL | Description |
|---|---|
| `POST /mikopbx-webhook/call` | Receive call events from MikoPBX |

---

## Facade Usage

```php
use BitDreamIT\MikoPBX\Facades\MikoPBX;

// ── Active calls (REST API v3) ─────────────────────────────────────────────
$response = MikoPBX::api()->getActiveCalls();
// $response = ['result' => true, 'data' => [...active calls...]]

// ── Call control (AMI — REST v3 has no call control endpoints) ─────────────
MikoPBX::originate('101', '01711000000');         // Extension 101 → customer
MikoPBX::transfer('PJSIP/101-00000001', '102');   // Transfer to ext 102
MikoPBX::hangup('PJSIP/101-00000001');            // End the call

// Or via ami() service directly:
MikoPBX::ami()->connect();
MikoPBX::ami()->originate('101', '01711000000');
MikoPBX::ami()->disconnect();

// ── CDR records (REST API v3) ──────────────────────────────────────────────
$response = MikoPBX::api()->getCDR('2026-06-01 00:00:00', '2026-06-30 23:59:59', [
    'src_num'     => '01711000000',  // filter by caller
    'disposition' => 'ANSWERED',     // ANSWERED | NO ANSWER | BUSY | FAILED
    'limit'       => 50,
    'offset'      => 0,
]);
// Each record has: src_num, dst_num, UNIQUEID, disposition, duration, billsec,
//                  recordingfile, playback_url, download_url, start, endtime

// ── Extensions (REST API v3) ───────────────────────────────────────────────
$exts = MikoPBX::api()->getExtensions(); // GET /v3/extensions:getForSelect
// Returns: [{"value": "101", "text": "101 John Smith"}, ...]

$statuses = MikoPBX::api()->getExtensionStatuses(); // GET /v3/sip:getPeersStatuses
// Each item: { id: "101", state: "OK|REGISTERED|UNREACHABLE|...", ipaddress, port }

// ── SIP trunk status ───────────────────────────────────────────────────────
$trunks = MikoPBX::api()->getTrunkStatus(); // GET /v3/sip-providers:getStatuses
$isUp   = collect($trunks['data'] ?? [])->contains(fn($t) => $t['state'] === 'REGISTERED');

// ── Campaigns ─────────────────────────────────────────────────────────────
$campaign = MikoPBX::campaign()->create(
    ['name' => 'June Promo', 'type' => 'voice_broadcast', 'max_channels' => 5],
    ['01711000001', '01711000002', '01711000003']
);
MikoPBX::campaign()->start($campaign);
MikoPBX::campaign()->pause($campaign);
MikoPBX::campaign()->stop($campaign);

// ── Agents ────────────────────────────────────────────────────────────────
$agents = MikoPBX::agent()->all();          // With live SIP status merged
$count  = MikoPBX::agent()->sync();         // Pull from MikoPBX → local DB

// ── Blacklist ─────────────────────────────────────────────────────────────
MikoPBX::blacklist()->add('01711999999', 'Spam caller', 'both');
$blocked = MikoPBX::blacklist()->isBlocked('01711999999');
MikoPBX::blacklist()->remove('01711999999');

// ── Callbacks ─────────────────────────────────────────────────────────────
MikoPBX::callback()->schedule('01711000000', [
    'name'     => 'Customer Name',
    'priority' => 'urgent',        // low | normal | high | urgent
    'note'     => 'Called about order #1234',
]);

// ── Analytics ─────────────────────────────────────────────────────────────
$summary = MikoPBX::analytics()->summary('2026-06-01', '2026-06-30');
// Returns: total_calls, answered, missed, failed, asr%, avg_duration, inbound, outbound

// ── Health ────────────────────────────────────────────────────────────────
$health = MikoPBX::health()->check();
// Returns: ['status' => 'healthy', 'amiOk' => true, 'ariOk' => true, 'sipOk' => true, 'calls' => 3]

// ── IVR ───────────────────────────────────────────────────────────────────
$menus = MikoPBX::api()->getIVRMenus();  // GET /v3/ivr-menu
MikoPBX::api()->saveIVRMenu(['name' => 'Main Menu', 'nodes' => [...]]);

// ── Sound files ────────────────────────────────────────────────────────────
MikoPBX::api()->uploadAudio('/path/to/greeting.wav');  // POST /v3/sound-files:uploadFile
```

---

## Livewire Components

All components can be used standalone in any Blade view:

```blade
{{-- Live call board with transfer/hangup controls (polls every 5s + Echo) --}}
@livewire('mikopbx-live-call-board')

{{-- Agent status dots with click-to-call (polls every 10s + Echo) --}}
@livewire('mikopbx-agent-status-grid')

{{-- Campaign manager with start/pause/stop (polls every 8s) --}}
@livewire('mikopbx-campaign-manager')

{{-- CDR table with live search and filter (updates on Echo events) --}}
@livewire('mikopbx-call-log-table')

{{-- Incoming call popup — auto-shows when a call arrives via Echo --}}
@livewire('mikopbx-incoming-popup')

{{-- Blacklist add/remove (Livewire) --}}
@livewire('mikopbx-blacklist-manager')

{{-- Pending callbacks with attempt/cancel --}}
@livewire('mikopbx-pending-callbacks')

{{-- Visual IVR node builder --}}
@livewire('mikopbx-ivr-builder')

{{-- Analytics charts dashboard with date filter --}}
@livewire('mikopbx-analytics-dash')

{{-- Health monitor — polls every 60s --}}
@livewire('mikopbx-health-monitor')
```

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan mikopbx:install` | Full setup wizard (publish config, run migrations, write Supervisor config) |
| `php artisan mikopbx:listen` | AMI daemon — run via Supervisor in production |
| `php artisan mikopbx:cdr-sync --days=1` | Pull CDR from MikoPBX REST API v3 and store locally |
| `php artisan mikopbx:sync-extensions` | Pull extensions from MikoPBX and upsert local DB |
| `php artisan mikopbx:campaign-run` | Start campaigns that are scheduled and due |
| `php artisan mikopbx:campaign-run --sync` | Sync progress of all running campaigns |
| `php artisan mikopbx:health` | Run health check (exit code 1 on critical) |

### Scheduler (optional)

Add to your `routes/console.php` or `App\Console\Kernel`:

```php
Schedule::command('mikopbx:cdr-sync')->hourly();
Schedule::command('mikopbx:campaign-run --sync')->everyFiveMinutes();
Schedule::command('mikopbx:health')->everyFiveMinutes();
```

---

## REST API v3 Endpoints Reference

Your MikoPBX server: `https://pbx.htncr.org` or `https://163.223.240.124`

**Authentication**: All requests need `Authorization: Bearer {API_KEY}` header.

**Response envelope** (every endpoint):
```json
{
  "result": true,
  "data": [...],
  "messages": { "error": [], "info": [], "warning": [] },
  "function": "...",
  "processor": "...",
  "pid": 12345
}
```

### CDR — Call Records

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/cdr` | List CDR with filters |
| GET | `/pbxcore/api/v3/cdr/{id}` | Single CDR record |
| DELETE | `/pbxcore/api/v3/cdr/{id}` | Delete CDR record |
| GET | `/pbxcore/api/v3/cdr:playback` | Stream recording audio |
| GET | `/pbxcore/api/v3/cdr:download` | Download recording file |
| GET | `/pbxcore/api/v3/cdr:getMetadata` | CDR column metadata |
| GET | `/pbxcore/api/v3/cdr:getStatsByProvider` | Stats by SIP provider |

GET `/pbxcore/api/v3/cdr` query parameters:

| Parameter | Required | Description |
|---|---|---|
| `limit` | No | Max records (default 20, max 100) |
| `offset` | No | Skip N records for pagination |
| `dateFrom` | No | Start date: `2026-06-01 00:00:00` |
| `dateTo` | No | End date: `2026-06-30 23:59:59` |
| `src_num` | No | Filter by caller number |
| `dst_num` | No | Filter by called number |
| `disposition` | No | `ANSWERED` / `NO ANSWER` / `BUSY` / `FAILED` |

### PBX Status

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/pbx-status:getActiveCalls` | Active calls right now |
| GET | `/pbxcore/api/v3/pbx-status:getActiveChannels` | Active Asterisk channels |

### Extensions & Employees

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/extensions:getForSelect` | Extensions as `{value, text}` |
| GET | `/pbxcore/api/v3/extensions` | Full extension list |
| GET | `/pbxcore/api/v3/extensions/{id}` | Single extension |
| GET | `/pbxcore/api/v3/employees` | All employees |
| POST | `/pbxcore/api/v3/employees` | Create employee |
| PUT | `/pbxcore/api/v3/employees/{id}` | Update employee |

### SIP Peer Status

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/sip:getPeersStatuses` | All SIP peers with state |
| GET | `/pbxcore/api/v3/sip:getRegistry` | SIP registration status |
| GET | `/pbxcore/api/v3/sip:getStatuses` | Combined SIP statuses |
| GET | `/pbxcore/api/v3/sip/{id}:getStatus` | Single peer status |
| GET | `/pbxcore/api/v3/sip/{id}:getStats` | Peer call statistics |

SIP peer state values: `OK` | `REGISTERED` | `UNREACHABLE` | `LAGGED` | `UNKNOWN` | `OFF`

### SIP Providers (AMARIP trunk)

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/sip-providers:getStatuses` | All trunk registration states |
| GET | `/pbxcore/api/v3/sip-providers` | Full trunk list |
| GET | `/pbxcore/api/v3/sip-providers/{id}` | Single trunk |
| GET | `/pbxcore/api/v3/sip-providers/{id}:getStatus` | Single trunk status |
| GET | `/pbxcore/api/v3/sip-providers/{id}:getStats` | Trunk call stats |
| POST | `/pbxcore/api/v3/sip-providers/{id}:forceCheck` | Force re-registration |

### IVR Menu

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/ivr-menu` | All IVR menus |
| POST | `/pbxcore/api/v3/ivr-menu` | Create IVR menu |
| GET | `/pbxcore/api/v3/ivr-menu/{id}` | Single IVR menu |
| PUT | `/pbxcore/api/v3/ivr-menu/{id}` | Update IVR menu |
| DELETE | `/pbxcore/api/v3/ivr-menu/{id}` | Delete IVR menu |
| GET | `/pbxcore/api/v3/ivr-menu:getDefault` | Default IVR template |

### Conference Rooms

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/conference-rooms` | All conference rooms |
| POST | `/pbxcore/api/v3/conference-rooms` | Create room |
| PUT | `/pbxcore/api/v3/conference-rooms/{id}` | Update room |
| DELETE | `/pbxcore/api/v3/conference-rooms/{id}` | Delete room |

### Call Queues

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/call-queues` | All queues |
| POST | `/pbxcore/api/v3/call-queues` | Create queue |
| PUT | `/pbxcore/api/v3/call-queues/{id}` | Update queue |
| DELETE | `/pbxcore/api/v3/call-queues/{id}` | Delete queue |

### Sound Files

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/sound-files:getForSelect` | As `{value, text}` list |
| POST | `/pbxcore/api/v3/sound-files:uploadFile` | Upload audio (multipart) |
| GET | `/pbxcore/api/v3/sound-files:playback` | Stream a sound file |

### System

| Method | Endpoint | Description |
|---|---|---|
| GET | `/pbxcore/api/v3/sysinfo:getInfo` | System info, disk, CPU, version |
| GET | `/pbxcore/api/v3/system:ping` | Health ping (no auth needed) |
| GET | `/pbxcore/api/v3/system:checkAuth` | Verify API key is valid |
| GET | `/pbxcore/api/v3/system:datetime` | Server datetime & timezone |
| GET | `/pbxcore/api/v3/system:checkForUpdates` | Firmware update check |

---

## How Call Control Works (AMI, not REST)

> **Critical:** MikoPBX REST API v3 (278 endpoints) has **no** originate, transfer, hangup, or mute endpoints. All call control goes through **AMI** (Asterisk Manager Interface) on TCP port 5038.

```
Your Laravel App
      │
      ├─ GET /v3/pbx-status:getActiveCalls  ──→  MikoPBX REST API (port 443)
      ├─ GET /v3/cdr                        ──→  MikoPBX REST API (port 443)
      ├─ GET /v3/extensions:getForSelect    ──→  MikoPBX REST API (port 443)
      │
      └─ Action: Originate  ───────────────→  MikoPBX AMI (TCP port 5038)
         Action: Redirect (transfer)       ──→  MikoPBX AMI (TCP port 5038)
         Action: Hangup                    ──→  MikoPBX AMI (TCP port 5038)
         Action: MuteAudio                 ──→  MikoPBX AMI (TCP port 5038)
         Event: Newchannel (listen)        ←──  MikoPBX AMI (TCP port 5038)
         Event: Hangup (listen)            ←──  MikoPBX AMI (TCP port 5038)
         Event: PeerStatus (listen)        ←──  MikoPBX AMI (TCP port 5038)
```

### Make a call from PHP

```php
use BitDreamIT\MikoPBX\Facades\MikoPBX;

// Method 1: Facade shortcut (connects, acts, disconnects)
MikoPBX::originate('101', '01711000000');

// Method 2: Direct AMI service
$ami = MikoPBX::ami();
$ami->connect();
$result = $ami->originate('101', '01711000000');
$ami->disconnect();

// Method 3: From a controller/job
app(\BitDreamIT\MikoPBX\Services\AMIService::class)->connect();
```

### AMI Event → Laravel Event

The `mikopbx:listen` daemon receives these AMI events and fires Laravel Events:

| AMI Event | Trigger | Laravel Event | Echo Channel |
|---|---|---|---|
| `Newchannel` | Phone rings | `IncomingCallEvent` | `mikopbx.calls` `.incoming` |
| `Bridge` | Call answered | `CallAnsweredEvent` | `mikopbx.calls` `.answered` |
| `Hangup` | Call ended | `CallEndedEvent` | `mikopbx.calls` `.ended` |
| `PeerStatus` | Agent login/out | `AgentStatusChangedEvent` | `mikopbx.agents` `.status` |

---

## CDR Field Names

> **Important**: MikoPBX REST API v3 uses different field names than older API versions.

| Our DB Column | MikoPBX v3 Field | Notes |
|---|---|---|
| `caller` | `src_num` | Caller phone number |
| `callee` | `dst_num` | Called phone number |
| `uniqueid` | `UNIQUEID` | Capital letters in API response |
| `channel` | `src_chan` | e.g. `PJSIP/101-00000001` |
| `started_at` | `start` | Call start time |
| `answered_at` | `answer` | Empty string if not answered |
| `ended_at` | `endtime` | Call end time |
| `status` | `disposition` | `ANSWERED` / `NO ANSWER` / `BUSY` / `FAILED` |
| `duration` | `duration` | Total seconds |
| `billsec` | `billsec` | Answered seconds |
| `recording_file` | `recordingfile` | Filename |
| `recording_url` | `playback_url` | Pre-signed stream URL |

---

## Testing with MikoPBXFake

`MikoPBXFake` replaces the MikoPBX service container binding. No real API or AMI calls during tests.

```php
use BitDreamIT\MikoPBX\Testing\MikoPBXFake;

class CallTest extends TestCase
{
    private MikoPBXFake $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = MikoPBXFake::make($this->app);
    }

    protected function tearDown(): void
    {
        $this->fake->reset();
        parent::tearDown();
    }

    public function test_placing_order_triggers_call(): void
    {
        $this->post('/orders', ['phone' => '01711000000']);

        $this->fake->assertOriginated('101', '01711000000');
    }

    public function test_no_call_when_blacklisted(): void
    {
        Blacklist::create(['number' => '01711999999', 'direction' => 'both']);

        $this->post('/orders', ['phone' => '01711999999']);

        $this->fake->assertNothingOriginated();
    }

    public function test_call_is_transferred(): void
    {
        $this->post('/calls/transfer', [
            'channel' => 'PJSIP/101-0001',
            'to'      => '102',
        ]);

        $this->fake->assertTransferred('PJSIP/101-0001', '102');
    }

    public function test_error_handling_when_ami_down(): void
    {
        $this->fake->failOnNextCall();

        $response = $this->post('/api/pbx/originate', [
            'from' => '101',
            'to'   => '01711000000',
        ]);

        $response->assertStatus(422);
        $this->fake->assertNothingOriginated();
    }
}
```

### Available assertions

| Method | Description |
|---|---|
| `assertOriginated($from, $to)` | Assert a call was originated |
| `assertNotOriginated($from, $to)` | Assert call was NOT made |
| `assertOriginateCount($n)` | Assert exactly N originate calls |
| `assertNothingOriginated()` | Assert zero calls were originated |
| `assertTransferred($channel, $to)` | Assert a transfer was performed |
| `assertHungUp($channel)` | Assert a channel was hung up |
| `assertCampaignStarted()` | Assert a campaign was started |
| `failOnNextCall()` | Make next originate throw exception |
| `reset()` | Clear all recorded calls between tests |

---

## Database Tables

All tables use the prefix `mikopbx_` (configurable in `config/mikopbx.php`).

| Table | Description |
|---|---|
| `mikopbx_extensions` | Agents / SIP extensions synced from MikoPBX |
| `mikopbx_call_logs` | CDR — local copy of call records |
| `mikopbx_campaigns` | Auto dialer campaigns |
| `mikopbx_campaign_numbers` | Numbers in each campaign with per-number status |
| `mikopbx_blacklist` | Blocked numbers with direction and expiry |
| `mikopbx_callbacks` | Scheduled callback tasks |
| `mikopbx_ivr_trees` | IVR node definitions |
| `mikopbx_conference_rooms` | Conference room config |
| `mikopbx_agent_status_log` | Agent status change history |
| `mikopbx_health_logs` | Health check results over time |

---

## Real-time Events (Laravel Echo)

Set up Laravel Echo in your `resources/js/bootstrap.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Echo = new Echo({
    broadcaster: 'reverb',        // or 'pusher'
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
```

Then import the package's echo listeners in your `app.js`:

```js
import { initMikoPBXEcho } from './mikopbx/echo-listeners.js';

document.addEventListener('DOMContentLoaded', () => {
    if (window.Echo) {
        initMikoPBXEcho(window.Echo);
    }
});
```

The package listens on:

| Channel | Event | Trigger |
|---|---|---|
| `mikopbx.calls` | `.incoming` | Incoming call (fires IncomingCallPopup) |
| `mikopbx.calls` | `.answered` | Call was answered |
| `mikopbx.calls` | `.ended` | Call ended/missed |
| `mikopbx.agents` | `.status` | Agent went online/offline |

---

## Package Structure

```
bitdreamit/laravel-mikopbx/
├── composer.json
├── README.md
├── CHANGELOG.md
├── config/
│   └── mikopbx.php                    All configuration keys
├── database/
│   └── migrations/
│       └── 2026_01_01_000001_...php   All 10 tables in one migration
├── routes/
│   ├── web.php                        25 named routes /pbx/*
│   ├── api.php                        JSON API routes /api/pbx/*
│   └── webhook.php                    /mikopbx-webhook/* (no auth)
├── resources/
│   ├── views/mikopbx/
│   │   ├── layouts/app.blade.php      Master layout: sidebar + web dialer + toasts
│   │   ├── dashboard/index.blade.php  Dashboard with task manager & follow-up list
│   │   ├── calls/                     index.blade.php, show.blade.php
│   │   ├── campaigns/                 index, create, show
│   │   ├── agents/index.blade.php
│   │   ├── analytics/index.blade.php
│   │   ├── recordings/index.blade.php
│   │   ├── blacklist/index.blade.php
│   │   ├── callbacks/index.blade.php
│   │   ├── conference/index.blade.php
│   │   ├── ivr/                       index, builder
│   │   ├── health/index.blade.php
│   │   └── livewire/                  10 Livewire blade views
│   ├── js/mikopbx/
│   │   ├── app.js                     Main entry: ringtone, Echo init, dial events
│   │   ├── echo-listeners.js          Reverb/Pusher channel subscriptions
│   │   └── click-to-call.js           Alpine.js component + [data-pbx-call] wiring
│   └── css/
│       └── mikopbx.css                Animations, waveform, status dots
└── src/
    ├── MikoPBXServiceProvider.php     Package bootstrap
    ├── MikoPBXManager.php             Facade target (14 services)
    ├── Facades/MikoPBX.php
    ├── Services/
    │   ├── RestApiService.php         MikoPBX REST API v3 (correct endpoints)
    │   ├── AMIService.php             TCP socket AMI — call control & events
    │   ├── ARIService.php             ARI REST + WebSocket URL
    │   ├── CampaignService.php        Campaign CRUD + start/pause/stop
    │   ├── AgentService.php           Agent list + sync + status
    │   ├── AnalyticsService.php       Summary, trend, peak hours, agents
    │   ├── BlacklistService.php       Add/remove/check blacklist
    │   ├── CallbackService.php        Schedule + attempt callbacks
    │   ├── RecordingService.php       List + proxy stream recordings
    │   ├── ConferenceService.php      Rooms + kick/mute
    │   ├── IVRService.php             IVR menu CRUD
    │   ├── HealthCheckService.php     AMI + SIP + REST health check
    │   ├── SmsService.php             SSL Wireless + Twilio SMS
    │   └── WebDialerService.php       SIP.js config builder
    ├── Models/                        7 models (all with dynamic getTable())
    ├── Http/Controllers/              12 controllers
    ├── Livewire/                      10 Livewire v3 components
    ├── Commands/                      6 Artisan commands
    ├── Events/                        4 ShouldBroadcast events
    ├── Jobs/ProcessCallbackJob.php
    ├── Listeners/MissedCallListener.php
    ├── Enums/                         CallStatus, CampaignStatus, AgentStatus
    ├── Traits/HasMikoPBXExtension.php Add to User model
    ├── Exceptions/MikoPBXException.php
    └── Testing/MikoPBXFake.php        Full test double
```

---

## Publishing to GitHub & Packagist

### 1. Create GitHub repository

```bash
cd your-package-directory
git init
git add .
git commit -m "feat: initial release v1.0.0"
git remote add origin https://github.com/bitdreamit/laravel-mikopbx.git
git push -u origin main
git tag v1.0.0
git push origin v1.0.0
```

### 2. Register on Packagist

1. Go to [packagist.org](https://packagist.org)
2. Login with GitHub
3. Click **Submit**
4. Enter: `https://github.com/bitdreamit/laravel-mikopbx`
5. Packagist reads `composer.json` and publishes `bitdreamit/laravel-mikopbx`

### 3. Anyone installs it

```bash
composer require bitdreamit/laravel-mikopbx
php artisan mikopbx:install
```

---

## Troubleshooting

### AMI connection failed

```
MikoPBX AMI: Cannot connect to 163.223.240.124:5038
```

Checks:
- Port 5038 is open on MikoPBX VPS firewall
- `MIKOPBX_AMI_HOST` points to the correct IP
- AMI user created in MikoPBX Admin → System → AMI Users
- Laravel server IP is in the allowed IP list

```bash
# Test from your Laravel server:
telnet 163.223.240.124 5038
# Should show: Asterisk Call Manager/...
```

### API key 401 Unauthorized

```
MikoPBX API error [401] GET /pbxcore/api/v3/cdr
```

Checks:
- `MIKOPBX_API_KEY` is set and correct
- The key was generated in MikoPBX Admin → Settings → API Keys
- The key has not expired

```bash
# Test with curl:
curl -k -H "Authorization: Bearer YOUR_KEY" https://163.223.240.124/pbxcore/api/v3/system:ping
# Should return: {"result":true,"data":{"PONG":"..."},...}
```

### SSL certificate error

Set `MIKOPBX_VERIFY_SSL=false` — MikoPBX uses a self-signed certificate by default.

### AMI listener not starting

```bash
sudo supervisorctl status mikopbx-ami
# If FATAL: check the error log
tail -100 storage/logs/mikopbx-ami-err.log
```

### Extensions not syncing

```bash
php artisan mikopbx:sync-extensions
# If 0 extensions: verify the API key has extensions read permission
# Test manually:
curl -k -H "Authorization: Bearer YOUR_KEY" https://163.223.240.124/pbxcore/api/v3/extensions:getForSelect
```

### CDR sync getting wrong field names

This package uses the correct MikoPBX v3 field names: `src_num`, `dst_num`, `UNIQUEID`, `start`, `disposition`. If you were using an older version that used `caller`, `dst`, `uniqueid`, `calldate` — those were wrong. Run a fresh sync:

```bash
php artisan mikopbx:cdr-sync --days=7
```

---

## License

MIT — free for commercial use.

Built by [BitDream IT](https://bitdreamit.com), Bangladesh.

GitHub: [github.com/bitdreamit/laravel-mikopbx](https://github.com/bitdreamit/laravel-mikopbx)
