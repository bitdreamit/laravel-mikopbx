<?php

namespace BitDreamIT\MikoPBX\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Services\{CallbackService, SmsService};

class ProcessCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public CallLog $callLog) {}

    public function handle(CallbackService $callbacks, SmsService $sms): void
    {
        // Create callback record
        $callbacks->scheduleFromMissedCall($this->callLog);

        // Send SMS alert to agent / supervisor
        if (config('mikopbx.features.sms_alerts')) {
            $sms->send(
                config('mikopbx.sms.from'),
                "Missed call from {$this->callLog->caller} at " . now()->format('H:i')
            );
        }
    }
}
