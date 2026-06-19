<?php

namespace BitDreamIT\MikoPBX\Facades;

use Illuminate\Support\Facades\Facade;
use BitDreamIT\MikoPBX\MikoPBXManager;
use BitDreamIT\MikoPBX\Services\{RestApiService,AMIService,ARIService,CampaignService,AgentService,RecordingService,ConferenceService,BlacklistService,AnalyticsService,CallbackService,HealthCheckService};
use BitDreamIT\MikoPBX\Services\IVRBuilder;

/**
 * @method static RestApiService     call()
 * @method static AMIService         ami()
 * @method static ARIService         ari()
 * @method static CampaignService    campaign()
 * @method static AgentService       agent()
 * @method static RecordingService   recording()
 * @method static ConferenceService  conference()
 * @method static BlacklistService   blacklist()
 * @method static AnalyticsService   analytics()
 * @method static CallbackService    callback()
 * @method static HealthCheckService health()
 * @method static IVRBuilder         ivr(string $name)
 * @see MikoPBXManager
 */
class MikoPBX extends Facade
{
    protected static function getFacadeAccessor(): string { return 'mikopbx'; }
}
