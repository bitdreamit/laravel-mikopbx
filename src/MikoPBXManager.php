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
 *   MikoPBX::call()->originate('101', '01711000000');
 *   MikoPBX::campaign()->create([...]);
 *   MikoPBX::agent()->all();
 */
class MikoPBXManager
{
    public function __construct(protected Application $app) {}

    public function api(): RestApiService       { return $this->app->make(RestApiService::class); }
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

    // Convenience shortcuts
    public function originate(string $from, string $to): array
    {
        return $this->api()->originate($from, $to);
    }

    public function transfer(string $channel, string $to): array
    {
        return $this->api()->transfer($channel, $to);
    }

    public function hangup(string $channel): array
    {
        return $this->api()->hangup($channel);
    }

    public function activeCalls(): array
    {
        return $this->api()->getActiveCalls();
    }

    public function extensions(): array
    {
        return $this->api()->getExtensions();
    }
}
