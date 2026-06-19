<?php

namespace BitDreamIT\MikoPBX;

use BitDreamIT\MikoPBX\Services\{RestApiService,AMIService,ARIService,CampaignService,AgentService,RecordingService,ConferenceService,BlacklistService,AnalyticsService,CallbackService,HealthCheckService};
use BitDreamIT\MikoPBX\Services\IVRBuilder;

/**
 * MikoPBX Laravel Manager — bitdreamit/laravel-mikopbx
 *
 * @method static RestApiService    call()
 * @method static AMIService        ami()
 * @method static ARIService        ari()
 * @method static CampaignService   campaign()
 * @method static AgentService      agent()
 * @method static RecordingService  recording()
 * @method static ConferenceService conference()
 * @method static BlacklistService  blacklist()
 * @method static AnalyticsService  analytics()
 * @method static CallbackService   callback()
 * @method static HealthCheckService health()
 * @method static IVRBuilder        ivr(string $name)
 */
class MikoPBXManager
{
    public function __construct(
        private RestApiService     $rest,
        private AMIService         $ami,
        private ARIService         $ari,
        private CampaignService    $campaign,
        private AgentService       $agent,
        private RecordingService   $recording,
        private ConferenceService  $conference,
        private BlacklistService   $blacklist,
        private AnalyticsService   $analytics,
        private CallbackService    $callback,
        private HealthCheckService $health,
    ) {}

    public function call(): RestApiService        { return $this->rest; }
    public function ami(): AMIService             { return $this->ami; }
    public function ari(): ARIService             { return $this->ari; }
    public function campaign(): CampaignService   { return $this->campaign; }
    public function agent(): AgentService         { return $this->agent; }
    public function recording(): RecordingService { return $this->recording; }
    public function conference(): ConferenceService { return $this->conference; }
    public function blacklist(): BlacklistService { return $this->blacklist; }
    public function analytics(): AnalyticsService { return $this->analytics; }
    public function callback(): CallbackService   { return $this->callback; }
    public function health(): HealthCheckService  { return $this->health; }
    public function ivr(string $name): IVRBuilder { return IVRBuilder::make($name); }
}
