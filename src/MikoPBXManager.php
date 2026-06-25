<?php

namespace BitDreamIT\MikoPBX;

use Illuminate\Foundation\Application;
use BitDreamIT\MikoPBX\Services\{
    AMIService, RestApiService, ARIService, CampaignService,
    AgentService, RecordingService, BlacklistService, CallbackService,
    ConferenceService, IVRService, AnalyticsService, HealthCheckService,
    SmsService, WebDialerService
};

/**
 * MikoPBXManager — central access point for all services.
 *
 * Usage:
 *   MikoPBX::api()->getActiveCalls();            // REST API v3
 *   MikoPBX::ami()->originate('101', '017...');  // AMI (call control)
 *   MikoPBX::campaign()->create([...]);           // Campaign service
 *   MikoPBX::agent()->all();                      // Agent service
 *
 * IMPORTANT — call control goes through AMI, not REST API:
 *   originate()  → AMI Action: Originate
 *   transfer()   → AMI Action: Redirect
 *   hangup()     → AMI Action: Hangup
 *   mute()       → AMI Action: MuteAudio
 */
class MikoPBXManager
{
    public function __construct(protected Application $app) {}

    public function api(): RestApiService        { return $this->app->make(RestApiService::class); }
    public function ami(): AMIService            { return $this->app->make(AMIService::class); }
    public function ari(): ARIService            { return $this->app->make(ARIService::class); }
    public function campaign(): CampaignService  { return $this->app->make(CampaignService::class); }
    public function agent(): AgentService        { return $this->app->make(AgentService::class); }
    public function recording(): RecordingService{ return $this->app->make(RecordingService::class); }
    public function blacklist(): BlacklistService{ return $this->app->make(BlacklistService::class); }
    public function callback(): CallbackService  { return $this->app->make(CallbackService::class); }
    public function conference(): ConferenceService{ return $this->app->make(ConferenceService::class); }
    public function ivr(): IVRService            { return $this->app->make(IVRService::class); }
    public function analytics(): AnalyticsService{ return $this->app->make(AnalyticsService::class); }
    public function health(): HealthCheckService { return $this->app->make(HealthCheckService::class); }
    public function sms(): SmsService            { return $this->app->make(SmsService::class); }
    public function dialer(): WebDialerService   { return $this->app->make(WebDialerService::class); }

    // ── Convenience shortcuts ─────────────────────────────────────────────────
    // All call control uses AMI (REST API v3 has no originate/transfer/hangup)

    /**
     * Originate a call from an extension to a number.
     * Connects to AMI, originates, disconnects.
     */
    public function originate(string $from, string $to): array
    {
        $ami = $this->ami();
        $ami->connect();
        $result = $ami->originate($from, $to);
        $ami->disconnect();
        return $result;
    }

    /**
     * Transfer an active channel to another extension.
     */
    public function transfer(string $channel, string $to, string $context = 'from-internal'): array
    {
        $ami = $this->ami();
        $ami->connect();
        $result = $ami->redirect($channel, $to, $context);
        $ami->disconnect();
        return $result;
    }

    /**
     * Hangup an active channel.
     */
    public function hangup(string $channel): array
    {
        $ami = $this->ami();
        $ami->connect();
        $result = $ami->hangup($channel);
        $ami->disconnect();
        return $result;
    }

    /**
     * Get all currently active calls (REST API v3).
     * Endpoint: GET /pbxcore/api/v3/pbx-status:getActiveCalls
     */
    public function activeCalls(): array
    {
        return $this->api()->getActiveCalls();
    }

    /**
     * Get extensions as {value, text} list (REST API v3).
     * Endpoint: GET /pbxcore/api/v3/extensions:getForSelect
     */
    public function extensions(): array
    {
        return $this->api()->getExtensions();
    }
}
