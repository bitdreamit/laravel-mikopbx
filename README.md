# bitdreamit/laravel-mikopbx

> **The most complete Laravel package for MikoPBX** — a production-ready, open-source call center platform with auto dialer, campaigns, live agent panel, web softphone, IVR builder, analytics, recordings, blacklist, callbacks & more.

[![License: MIT](https://img.shields.io/badge/License-MIT-indigo.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%2F12-red.svg)](https://laravel.com)
[![MikoPBX](https://img.shields.io/badge/MikoPBX-2024%2B-orange.svg)](https://mikopbx.com)

---

## ✨ Features

| Feature | Description |
|---|---|
| 📞 **Live Call Board** | Real-time active calls with transfer, mute, hangup |
| 👥 **Agent Management** | Status grid, click-to-call, status change, sync from MikoPBX |
| 📢 **Auto Dialer** | Create campaigns, upload number lists, voice broadcast, interactive surveys |
| 🌿 **IVR Builder** | Visual node editor — Press 1 for Sales, Press 2 for Support |
| 📊 **Analytics** | Daily trend, peak hours, ASR, agent performance charts |
| 🎙️ **Recordings** | Proxy stream, audio player, download, search by number/date |
| 🚫 **Blacklist** | Block inbound/outbound numbers with expiry support |
| 📅 **Callbacks** | Schedule, prioritise, attempt, assign to agent |
| 🎙️ **Conference** | Room list, kick/mute participants, dial-in |
| ❤️ **Health Monitor** | AMI/ARI/SIP status check with history |
| 📱 **Web Dialer** | Browser SIP softphone powered by SIP.js |
| 🔔 **Real-time Events** | Laravel Echo (Reverb/Pusher) — incoming call popup, agent status |
| 🧪 **MikoPBXFake** | Full test double with assertions for CI/CD |

---

## 📦 Installation

```bash
composer require bitdreamit/laravel-mikopbx
php artisan mikopbx:install
```

`mikopbx:install` will:
- Publish `config/mikopbx.php`
- Run migrations (8 tables, all prefixed `mikopbx_`)
- Write a Supervisor config to `docs/supervisor-mikopbx-ami.conf`
- Write `.env.mikopbx.example` with all required variables

---

## ⚙️ Configuration

Add to your `.env`:

```env
# MikoPBX REST API
MIKOPBX_URL=https://YOUR-MIKOPBX-VPS-IP
MIKOPBX_API_KEY=your-64-char-api-key

# AMI (live call events)
MIKOPBX_AMI_HOST=YOUR-MIKOPBX-VPS-IP
MIKOPBX_AMI_PORT=5038
MIKOPBX_AMI_USER=laravelapp
MIKOPBX_AMI_SECRET=your-strong-secret

# ARI (optional — WebRTC/channel control)
MIKOPBX_ARI_URL=http://YOUR-MIKOPBX-VPS-IP:8088
MIKOPBX_ARI_USER=admin
MIKOPBX_ARI_PASSWORD=your-ari-password

# Web Dialer (SIP.js softphone)
MIKOPBX_DIALER_ENABLED=true
MIKOPBX_SIP_SERVER=YOUR-MIKOPBX-VPS-IP
MIKOPBX_SIP_WS_PORT=8088
```

**Enable AMI in MikoPBX:**
> Admin Panel → System → Asterisk Managers → Add
> Username: `laravelapp` | Secret: `your-strong-secret` | Allowed IP: your Laravel server IP

---

## 🔌 Enable AMI Listener (Supervisor)

```bash
# Copy supervisor config
sudo cp docs/supervisor-mikopbx-ami.conf /etc/supervisor/conf.d/

# Load it
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mikopbx-ami

# Verify
sudo supervisorctl status mikopbx-ami
```

---

## 🌐 Access the Call Center

Visit `https://yourapp.com/pbx` in your browser.

| Page | URL |
|---|---|
| Dashboard | `/pbx` |
| Call Logs | `/pbx/calls` |
| Campaigns | `/pbx/campaigns` |
| Agents | `/pbx/agents` |
| Analytics | `/pbx/analytics` |
| Recordings | `/pbx/recordings` |
| IVR Builder | `/pbx/ivr/builder` |
| Blacklist | `/pbx/blacklist` |
| Callbacks | `/pbx/callbacks` |
| Conference | `/pbx/conference` |
| Health | `/pbx/health` |

---

## 🔧 Facade Usage

```php
use BitDreamIT\MikoPBX\Facades\MikoPBX;

// Make a click-to-call from extension 101 to a customer
MikoPBX::originate('101', '01711000000');

// Transfer active call
MikoPBX::transfer('PJSIP/101-0001', '102');

// Get live active calls
$calls = MikoPBX::activeCalls();

// Create and start a campaign
$campaign = MikoPBX::campaign()->create(
    ['name' => 'June Promo', 'type' => 'voice_broadcast', 'max_channels' => 5],
    ['01711000001', '01711000002', '01711000003']
);
MikoPBX::campaign()->start($campaign);

// Add to blacklist
MikoPBX::blacklist()->add('01711999999', 'Spam');

// Schedule callback
MikoPBX::callback()->schedule('01711000000', ['priority' => 'urgent']);

// Analytics
$summary = MikoPBX::analytics()->summary('2026-06-01', '2026-06-30');

// Health check
$health = MikoPBX::health()->check();
```

---

## 🧩 Livewire Components

```blade
{{-- Live call board with transfer/hangup controls --}}
@livewire('mikopbx-live-call-board')

{{-- Agent status grid with click-to-call --}}
@livewire('mikopbx-agent-status-grid')

{{-- Campaign manager with start/pause/stop --}}
@livewire('mikopbx-campaign-manager')

{{-- Full CDR table with filters --}}
@livewire('mikopbx-call-log-table')

{{-- Incoming call popup (auto-shows on incoming call) --}}
@livewire('mikopbx-incoming-popup')

{{-- Visual IVR node builder --}}
@livewire('mikopbx-ivr-builder')

{{-- Analytics dashboard with charts --}}
@livewire('mikopbx-analytics-dash')

{{-- Health monitor --}}
@livewire('mikopbx-health-monitor')
```

---

## 🧪 Testing

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

    public function test_order_triggers_call(): void
    {
        // Act
        $this->post('/orders', ['customer_phone' => '01711000000']);

        // Assert
        $this->fake->assertOriginated('101', '01711000000');
    }
}
```

Run tests:
```bash
php artisan test
# or
./vendor/bin/pest
```

---

## 📁 Package Structure

```
bitdreamit/laravel-mikopbx/
├── config/mikopbx.php                  # All configuration
├── database/migrations/                # 8 tables
├── routes/
│   ├── web.php                         # /pbx/* pages
│   ├── api.php                         # /api/pbx/* JSON endpoints
│   └── webhook.php                     # /mikopbx-webhook/* (no auth)
├── resources/
│   ├── views/mikopbx/                  # All Blade views
│   │   ├── layouts/app.blade.php       # Master layout + sidebar + web dialer
│   │   ├── dashboard/                  # Dashboard with task manager
│   │   ├── calls/                      # CDR index + show
│   │   ├── campaigns/                  # Index, create, show
│   │   ├── agents/                     # Agent grid
│   │   ├── analytics/                  # Charts dashboard
│   │   ├── recordings/                 # Audio player
│   │   ├── blacklist/                  # Blacklist manager
│   │   ├── callbacks/                  # Callback scheduler
│   │   ├── conference/                 # Conference rooms
│   │   ├── ivr/                        # IVR builder
│   │   ├── health/                     # System health
│   │   └── livewire/                   # All Livewire blade views
│   ├── js/mikopbx/
│   │   ├── app.js                      # Main JS entry + ringtone
│   │   ├── echo-listeners.js           # Laravel Echo channel bindings
│   │   └── click-to-call.js            # Alpine.js call helper
│   └── css/mikopbx.css                 # Animations + custom styles
└── src/
    ├── MikoPBXServiceProvider.php
    ├── MikoPBXManager.php              # Facade target (14 services)
    ├── Facades/MikoPBX.php
    ├── Services/                       # 14 service classes
    ├── Models/                         # 7 Eloquent models
    ├── Http/Controllers/               # 12 controllers
    ├── Livewire/                       # 10 Livewire components
    ├── Commands/                       # 6 Artisan commands
    ├── Events/                         # 4 broadcast events
    ├── Jobs/                           # ProcessCallbackJob
    ├── Listeners/                      # MissedCallListener
    ├── Enums/                          # CallStatus, CampaignStatus, AgentStatus
    ├── Traits/                         # HasMikoPBXExtension
    ├── Exceptions/                     # MikoPBXException
    └── Testing/MikoPBXFake.php         # Full test double
```

---

## 🔁 Artisan Commands

| Command | Description |
|---|---|
| `mikopbx:install` | Full installation wizard |
| `mikopbx:listen` | AMI daemon (run via Supervisor) |
| `mikopbx:cdr-sync --days=1` | Sync call records from MikoPBX |
| `mikopbx:sync-extensions` | Pull extensions from MikoPBX |
| `mikopbx:campaign-run` | Start scheduled campaigns |
| `mikopbx:campaign-run --sync` | Sync progress of running campaigns |
| `mikopbx:health` | Run health check |

---

## 🌐 Real-time (Laravel Reverb / Pusher)

Add to your `config/broadcasting.php` and ensure `BROADCAST_CONNECTION=reverb` (self-hosted, free) or `pusher`.

Channels:
- `mikopbx.calls` — events: `incoming`, `answered`, `ended`
- `mikopbx.agents` — events: `status`

---

## 🔒 Security

- All routes protected by `auth` middleware by default
- Webhook secured by `MIKOPBX_WEBHOOK_SECRET` header
- Recording proxy prevents direct VPS access
- AMI credentials kept server-side only

---

## 📜 License

MIT — free for commercial use. Built by [BitDream IT](https://bitdreamit.com), Bangladesh.

GitHub: [github.com/bitdreamit/laravel-mikopbx](https://github.com/bitdreamit/laravel-mikopbx)
