<?php

namespace BitDreamIT\MikoPBX\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use BitDreamIT\MikoPBX\Models\CallbackRequest;
use BitDreamIT\MikoPBX\Services\CallbackService;

class ProcessCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(private CallbackRequest $callback) {}

    public function handle(CallbackService $service): void
    {
        if (!$this->callback->canRetry()) {
            $this->callback->update(['status' => 'failed']);
            return;
        }
        $success = $service->execute($this->callback);
        if (!$success && $this->callback->canRetry()) {
            self::dispatch($this->callback->fresh())->delay(now()->addMinutes(config('mikopbx.retry_delay_minutes', 5)));
        }
    }
}
