<?php

namespace BitDreamIT\MikoPBX\Listeners;

use BitDreamIT\MikoPBX\Events\CallEndedEvent;
use BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob;
use BitDreamIT\MikoPBX\Services\SmsService;

class MissedCallListener
{
    public function __construct(private SmsService $sms) {}

    public function handle(CallEndedEvent $event): void
    {
        if ($event->callLog->status !== 'missed') return;

        // Schedule a callback
        ProcessCallbackJob::dispatch($event->callLog)
            ->delay(now()->addMinutes(2));

        // SMS alert if enabled
        if (config('mikopbx.features.sms_alerts')) {
            $this->sms->send(
                config('mikopbx.sms.from', ''),
                "Missed call from {$event->callLog->caller} at " . now()->format('H:i')
            );
        }
    }
}
