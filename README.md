# bitdreamit/laravel-mikopbx

> The most complete, professional and premium open-source Laravel package for MikoPBX & Asterisk.
> Built for Laravel 12 · PHP 8.2+ · Zero external dependencies.

[![Latest Version](https://img.shields.io/packagist/v/bitdreamit/laravel-mikopbx.svg)](https://packagist.org/packages/bitdreamit/laravel-mikopbx)
[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-orange.svg)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## Features at a Glance

| Category | Features |
|---|---|
| **Call Control** | Originate, Transfer (blind + attended), Hangup, Hold, Mute, Park |
| **Queue Management** | Add/remove agents, pause/unpause, queue status, summary |
| **Auto Dialer** | Voice broadcast, IVR survey, predictive, campaign scheduling |
| **Conference** | Create bridge, dial-in, mute/kick participant, record |
| **IVR Builder** | Fluent API, multi-level, preset templates |
| **Recording** | List, download, live start/stop/pause, stats |
| **Analytics** | Dashboard KPIs, SLA compliance, peak hours, agent performance, CDR export |
| **Blacklist** | Block/unblock numbers, expiry, auto-reject |
| **Callbacks** | Auto-schedule missed call callbacks, retry queue |
| **Notifications** | Email, Slack, database for missed calls, voicemail, campaigns |
| **SMS Alerts** | Twilio, Vonage, SSL Wireless BD, custom gateway |
| **Real-time Events** | AMI listener, Laravel broadcasting, Laravel Echo ready |
| **ARI** | Channels, bridges, recordings, sounds, endpoints, playbacks |
| **AMI** | Full Asterisk AMI — all actions, queue, voicemail, parking, confbridge, AstDB |
| **REST API** | 40+ endpoints, form request validation, JSON resources |
| **Testing** | MikoPBXFake, simulate events, assert helpers |
| **Health Check** | REST + AMI + extensions + active calls check |
| **Traits** | HasCallLogs, FormatsCallDuration, ValidatesPhoneNumber |
| **DTOs** | CallDTO, OriginateDTO, CampaignDTO, AgentDTO |
| **Contracts** | All services behind interfaces for easy mocking |

---

## Installation

```bash
composer require bitdreamit/laravel-mikopbx
```

```bash
php artisan mikopbx:install
```

---

## Environment Variables

```env
# REST API
MIKOPBX_URL=https://YOUR-MIKOPBX-VPS-IP
MIKOPBX_API_KEY=your-64-char-api-key
MIKOPBX_VERIFY_SSL=false

# AMI (Asterisk Manager Interface)
MIKOPBX_AMI_HOST=YOUR-MIKOPBX-VPS-IP
MIKOPBX_AMI_PORT=5038
MIKOPBX_AMI_USER=laravelapp
MIKOPBX_AMI_SECRET=StrongSecret123

# ARI (Asterisk REST Interface) — optional
MIKOPBX_ARI_URL=http://YOUR-MIKOPBX-VPS-IP:8088
MIKOPBX_ARI_USER=ari_admin
MIKOPBX_ARI_SECRET=ari_secret

# Webhook
MIKOPBX_WEBHOOK_SECRET=your-hmac-secret

# SMS
MIKOPBX_SMS_DRIVER=custom   # twilio|vonage|ssl_bd|custom
```

---

## Enable AMI in MikoPBX

```
Admin Panel → System → Asterisk Managers → Add
Username    : laravelapp
Secret      : StrongSecret123
Allowed IP  : YOUR-LARAVEL-VPS-IP
Permissions : all
```

---

## Usage

### Facade Import

```php
use BitDreamIT\MikoPBX\Facades\MikoPBX;
```

---

### Call Control

```php
// Click-to-call: agent 101 calls customer
MikoPBX::call()->originate('101', '01711XXXXXX');

// Blind transfer
MikoPBX::ami()->blindTransfer('PJSIP/101-00000001', '102');

// Attended transfer (merge two calls)
MikoPBX::ami()->attendedTransfer('PJSIP/101-00000001', 'PJSIP/102-00000002');

// Mute / unmute
MikoPBX::ami()->mute('PJSIP/101-00000001', 'in');
MikoPBX::ami()->unmute('PJSIP/101-00000001', 'in');

// Hold / unhold via ARI
MikoPBX::ari()->holdChannel($channelId);
MikoPBX::ari()->unholdChannel($channelId);

// Park a call
MikoPBX::ami()->parkCall('PJSIP/101-00000001', 'PJSIP/101-00000001');
MikoPBX::ami()->getParkedCalls();

// Hangup
MikoPBX::ami()->hangup('PJSIP/101-00000001');

// Active calls
MikoPBX::call()->getActiveCalls();

// Send DTMF
MikoPBX::ami()->sendDTMF('PJSIP/101-00000001', '1');
```

---

### Queue Management

```php
MikoPBX::ami()->queueAdd('support', 'PJSIP/101', 'Rahim');
MikoPBX::ami()->queueRemove('support', 'PJSIP/101');
MikoPBX::ami()->queuePause('support', 'PJSIP/101', 'Lunch break');
MikoPBX::ami()->queueUnpause('support', 'PJSIP/101');
MikoPBX::ami()->queueStatus('support');
MikoPBX::ami()->queueSummary();
```

---

### Auto Dialer Campaigns

```php
// Simple voice broadcast
$campaign = MikoPBX::campaign()->broadcast(
    name: 'June Promo',
    numbers: ['01711XXXXXX', '01811XXXXXX'],
    audioFile: storage_path('app/promo.wav'),
    maxChannels: 5
);
MikoPBX::campaign()->start($campaign);
MikoPBX::campaign()->status($campaign);
MikoPBX::campaign()->stop($campaign);

// IVR Campaign (Press 1 = agent, Press 2 = unsubscribe)
$campaign = MikoPBX::campaign()->withIVR(
    name: 'Survey Campaign',
    numbers: $numbers,
    audioFile: storage_path('app/survey.wav'),
    keypressActions: [
        '1' => ['action' => 'transfer', 'value' => '101'],
        '2' => ['action' => 'hangup',   'value' => ''],
    ]
);
```

---

### IVR Builder

```php
$ivr = MikoPBX::ivr('Main Menu')
    ->greeting('welcome.wav')
    ->timeout(10)
    ->pressToTransfer(1, '101')
    ->pressToTransfer(2, '102')
    ->pressToQueue(3, '200')
    ->pressToVoicemail(9)
    ->pressToHangup(0)
    ->onTimeout('repeat')
    ->build();

// Preset templates
$ivr = IVRBuilder::salesSupportTemplate('101', '102', '104');
$ivr = IVRBuilder::surveyTemplate('103');
```

---

### Conference Calls

```php
$bridge = MikoPBX::conference()->create('Team Meeting');
MikoPBX::conference()->addParticipant($bridge['id'], $channelId);
MikoPBX::conference()->dialIn($bridge['id'], 'PJSIP/01711XXXXXX');
MikoPBX::conference()->muteParticipant($channelId);
MikoPBX::conference()->startRecording($bridge['id']);
MikoPBX::conference()->end($bridge['id']);
```

---

### Recording

```php
MikoPBX::recording()->getAll('2026-06-01', '2026-06-30');
MikoPBX::recording()->getToday('101');
MikoPBX::recording()->getStats('2026-06-01', '2026-06-30');
MikoPBX::recording()->startLiveRecording($channelId, 'call-name');
MikoPBX::recording()->stopLiveRecording('call-name');
```

---

### Analytics

```php
MikoPBX::analytics()->dashboard('2026-06-01', '2026-06-30');
MikoPBX::analytics()->peakHours('2026-06-01', '2026-06-30');
MikoPBX::analytics()->dailyTrend('2026-06-01', '2026-06-30');
MikoPBX::analytics()->agentPerformance('2026-06-01', '2026-06-30');
MikoPBX::analytics()->slaCompliance('2026-06-01', '2026-06-30', 20); // 20s SLA
MikoPBX::analytics()->abandonedCalls('2026-06-01', '2026-06-30');
MikoPBX::analytics()->topCallers('2026-06-01', '2026-06-30', 10);
MikoPBX::analytics()->weeklyComparison();
$csv = MikoPBX::analytics()->exportCsv('2026-06-01', '2026-06-30');
```

---

### Blacklist

```php
MikoPBX::blacklist()->block('01711XXXXXX', 'Spam caller');
MikoPBX::blacklist()->block('01811XXXXXX', 'Fraud', '2026-12-31'); // expires
MikoPBX::blacklist()->isBlocked('01711XXXXXX'); // true
MikoPBX::blacklist()->unblock('01711XXXXXX');
MikoPBX::blacklist()->getAll();
MikoPBX::blacklist()->cleanExpired();
```

---

### Callbacks

```php
// Schedule auto-callback for missed caller
MikoPBX::callback()->schedule('01711XXXXXX', '101', 5); // 5 min delay

// Get pending
MikoPBX::callback()->getPending();

// Cancel
MikoPBX::callback()->cancel($id);
```

---

### SMS Notifications

```php
use BitDreamIT\MikoPBX\Services\SmsNotificationService;

$sms = app(SmsNotificationService::class);
$sms->missedCallAlert('+8801711XXXXXX', '01811XXXXXX', '101');
$sms->voicemailAlert('+8801711XXXXXX', '01811XXXXXX');
$sms->campaignCompleted('+8801711XXXXXX', 'June Promo', 450, 500);
$sms->callbackReminder('+8801711XXXXXX', '01811XXXXXX');
```

---

### Laravel Notifications

```php
use BitDreamIT\MikoPBX\Notifications\MissedCallNotification;
use BitDreamIT\MikoPBX\Notifications\VoicemailNotification;
use BitDreamIT\MikoPBX\Notifications\CampaignCompletedNotification;

$agent->notify(new MissedCallNotification('01711XXXXXX', '101'));
$agent->notify(new VoicemailNotification('01711XXXXXX', '101', 45, 'rec.wav'));
$manager->notify(new CampaignCompletedNotification('June Promo', 500, 450, 50));
```

---

### HasCallLogs Trait (CRM integration)

```php
// In your Customer / Lead / Contact model:
use BitDreamIT\MikoPBX\Traits\HasCallLogs;

class Customer extends Model {
    use HasCallLogs;
    protected string $phoneColumn = 'mobile'; // column name
}

// Usage:
$customer->callLogs()->get();
$customer->missedCalls()->count();
$customer->lastCall();
$customer->totalCallDuration();
$customer->callNow('101'); // agent 101 calls this customer
$customer->hasMissedCalls();
```

---

### Voicemail

```php
MikoPBX::ami()->getVoicemailCount('101@default');
MikoPBX::ami()->mailboxStatus('101@default');
MikoPBX::ami()->listVoicemailUsers();
```

---

### AstDB

```php
MikoPBX::ami()->dbPut('CRM', 'customer_01711', 'VIP');
MikoPBX::ami()->dbGet('CRM', 'customer_01711');
MikoPBX::ami()->dbDelete('CRM', 'customer_01711');
```

---

### Call Monitoring (Supervisor Spy)

```php
MikoPBX::ami()->monitorStart('PJSIP/101-00000001', '/tmp/supervisor_rec');
MikoPBX::ami()->monitorPause('PJSIP/101-00000001');
MikoPBX::ami()->monitorResume('PJSIP/101-00000001');
MikoPBX::ami()->monitorStop('PJSIP/101-00000001');
```

---

### Health Check

```php
MikoPBX::health()->check();   // full check
MikoPBX::health()->ping();    // quick bool
MikoPBX::health()->systemInfo();
```

---

### System Commands

```php
MikoPBX::ami()->reloadDialplan();
MikoPBX::ami()->moduleReload('chan_pjsip.so');
MikoPBX::ami()->command('core show channels');
MikoPBX::ami()->getUptime();
MikoPBX::ami()->ping();
MikoPBX::call()->getVersion();
```

---

## Artisan Commands

```bash
php artisan mikopbx:install              # Install package
php artisan mikopbx:listen               # Start AMI event listener
php artisan mikopbx:listen --verbose     # With raw event output
php artisan mikopbx:health               # Health check
php artisan mikopbx:sync-extensions      # Sync extension statuses
php artisan mikopbx:cdr-sync             # Sync CDR for today
php artisan mikopbx:cdr-sync --from=2026-06-01 --to=2026-06-30
php artisan mikopbx:campaign start 1    # Start campaign ID 1
php artisan mikopbx:campaign stop  1    # Stop campaign
php artisan mikopbx:campaign status 1   # Show status
```

---

## REST API Endpoints (40+)

| Method | Endpoint | Description |
|---|---|---|
| GET | /mikopbx/calls/active | Live active calls |
| POST | /mikopbx/calls/originate | Make outbound call |
| POST | /mikopbx/calls/transfer | Transfer call (blind/attended) |
| POST | /mikopbx/calls/hangup | Hangup a channel |
| POST | /mikopbx/calls/mute | Mute channel |
| POST | /mikopbx/calls/park | Park a call |
| GET | /mikopbx/calls/parked | Get parked calls |
| GET | /mikopbx/calls/logs | Call log history (paginated, filterable) |
| GET | /mikopbx/calls/stats | Call statistics summary |
| GET | /mikopbx/campaigns | List campaigns |
| POST | /mikopbx/campaigns | Create campaign |
| POST | /mikopbx/campaigns/{id}/start | Start campaign |
| POST | /mikopbx/campaigns/{id}/stop | Stop campaign |
| GET | /mikopbx/campaigns/{id}/status | Campaign status |
| GET | /mikopbx/agents | All agents with status |
| GET | /mikopbx/agents/online | Online agents only |
| POST | /mikopbx/agents/{ext}/call | Agent calls a number |
| POST | /mikopbx/agents/{ext}/queue/pause | Pause agent in queue |
| GET | /mikopbx/recordings | List recordings |
| GET | /mikopbx/recordings/{file}/download | Download recording |
| POST | /mikopbx/conferences | Create conference |
| POST | /mikopbx/conferences/{id}/participants | Add participant |
| POST | /mikopbx/ivr/build | Build IVR menu |
| GET | /mikopbx/ivr/templates | Get preset IVR templates |
| GET | /mikopbx/analytics/dashboard | KPI dashboard |
| GET | /mikopbx/analytics/peak-hours | Peak call hours |
| GET | /mikopbx/analytics/agent-performance | Agent stats |
| GET | /mikopbx/analytics/sla | SLA compliance report |
| GET | /mikopbx/analytics/export | Download CDR as CSV |
| GET | /mikopbx/blacklist | Get blacklisted numbers |
| POST | /mikopbx/blacklist | Block a number |
| DELETE | /mikopbx/blacklist/{number} | Unblock a number |
| GET | /mikopbx/blacklist/check/{number} | Check if blocked |
| POST | /mikopbx/callbacks | Schedule callback |
| GET | /mikopbx/callbacks/pending | Pending callbacks |
| GET | /mikopbx/health | Full health check |
| GET | /mikopbx/health/ping | Quick ping |
| GET | /mikopbx/system/status | System info |
| POST | /mikopbx/system/reload | Reload dialplan |
| POST | /mikopbx/webhook | Receive MikoPBX events |

---

## Real-time Events (AMI Listener)

Run via Supervisor (see `docs/supervisor-mikopbx-ami.conf`):

```bash
php artisan mikopbx:listen
```

Subscribe in EventServiceProvider:

```php
use BitDreamIT\MikoPBX\Events\IncomingCallEvent;
use BitDreamIT\MikoPBX\Events\CallEndedEvent;

protected $listen = [
    IncomingCallEvent::class => [
        \App\Listeners\ShowCallPopupToAgent::class,
        \App\Listeners\LookupCustomerInCRM::class,
    ],
    CallEndedEvent::class => [
        \App\Listeners\SaveCallToCRM::class,
        \App\Listeners\SendSummaryEmail::class,
    ],
];
```

---

## Testing

```php
use BitDreamIT\MikoPBX\Testing\MikoPBXFake;

class CallTest extends TestCase
{
    public function test_incoming_call_creates_log(): void
    {
        MikoPBXFake::fake();

        MikoPBXFake::simulateIncomingCall('01711XXXXXX', '101');

        MikoPBXFake::assertIncomingCallFired('01711XXXXXX', '101');
        $this->assertDatabaseHas('mikopbx_call_logs', ['caller' => '01711XXXXXX', 'extension' => '101']);
    }

    public function test_missed_call_schedules_callback(): void
    {
        MikoPBXFake::fake();
        Queue::fake();

        MikoPBXFake::simulateMissedCall('01711XXXXXX', '101');

        Queue::assertPushed(\BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob::class);
    }
}
```

---

## Supervisor Setup (Production)

```ini
; /etc/supervisor/conf.d/mikopbx-ami.conf
[program:mikopbx-ami]
command=php /var/www/your-app/artisan mikopbx:listen
directory=/var/www/your-app
autostart=true
autorestart=true
user=www-data
stderr_logfile=/var/log/supervisor/mikopbx-ami.err.log
stdout_logfile=/var/log/supervisor/mikopbx-ami.out.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start mikopbx-ami
supervisorctl status mikopbx-ami
```

---

## Author

**BitDreamIT** — https://bitdreamit.com
Built in Bangladesh. Open source forever. MIT License.

---

## License

MIT — Free to use, modify, and distribute.
