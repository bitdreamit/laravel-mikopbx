<?php

namespace BitDreamIT\MikoPBX\Facades;

use Illuminate\Support\Facades\Facade;
use BitDreamIT\MikoPBX\MikoPBXManager;

/**
 * @method static \BitDreamIT\MikoPBX\Services\RestApiService api()
 * @method static \BitDreamIT\MikoPBX\Services\AMIService ami()
 * @method static \BitDreamIT\MikoPBX\Services\ARIService ari()
 * @method static \BitDreamIT\MikoPBX\Services\CampaignService campaign()
 * @method static \BitDreamIT\MikoPBX\Services\AgentService agent()
 * @method static \BitDreamIT\MikoPBX\Services\RecordingService recording()
 * @method static \BitDreamIT\MikoPBX\Services\BlacklistService blacklist()
 * @method static \BitDreamIT\MikoPBX\Services\CallbackService callback()
 * @method static \BitDreamIT\MikoPBX\Services\ConferenceService conference()
 * @method static \BitDreamIT\MikoPBX\Services\IVRService ivr()
 * @method static \BitDreamIT\MikoPBX\Services\AnalyticsService analytics()
 * @method static \BitDreamIT\MikoPBX\Services\HealthCheckService health()
 * @method static \BitDreamIT\MikoPBX\Services\SmsService sms()
 * @method static \BitDreamIT\MikoPBX\Services\WebDialerService dialer()
 * @method static array originate(string $from, string $to)
 * @method static array transfer(string $channel, string $to)
 * @method static array hangup(string $channel)
 * @method static array activeCalls()
 * @method static array extensions()
 *
 * @see \BitDreamIT\MikoPBX\MikoPBXManager
 */
class MikoPBX extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mikopbx';
    }
}
