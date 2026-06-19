<?php

namespace BitDreamIT\MikoPBX\Services;

use BitDreamIT\MikoPBX\Models\CallbackRequest;
use BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob;
use Illuminate\Support\Facades\Log;

/**
 * Callback Service
 * Automatically schedule and retry missed call callbacks.
 */
class CallbackService
{
    public function __construct(
        private RestApiService $api,
        private AMIService     $ami,
    ) {}

    /** Schedule a callback for a missed caller */
    public function schedule(string $callerNumber, string $extension = '', int $delayMinutes = 5): CallbackRequest
    {
        $callback = CallbackRequest::create([
            'caller_number' => $callerNumber,
            'extension'     => $extension,
            'status'        => 'pending',
            'scheduled_at'  => now()->addMinutes($delayMinutes),
            'max_attempts'  => config('mikopbx.max_retry_attempts', 3),
        ]);

        ProcessCallbackJob::dispatch($callback)->delay(now()->addMinutes($delayMinutes));

        Log::info("Callback scheduled for $callerNumber in {$delayMinutes} mins (ID: {$callback->id})");

        return $callback;
    }

    /** Execute a callback immediately */
    public function execute(CallbackRequest $callback): bool
    {
        $callback->increment('attempts');
        $callback->update(['status' => 'processing']);

        try {
            $result = $this->api->originate(
                $callback->extension ?: config('mikopbx.default_callback_extension', '100'),
                $callback->caller_number
            );

            $success = !empty($result['data']);
            $callback->update(['status' => $success ? 'completed' : 'failed', 'completed_at' => $success ? now() : null]);

            return $success;
        } catch (\Throwable $e) {
            Log::error("Callback failed for {$callback->caller_number}: " . $e->getMessage());
            $callback->update(['status' => 'failed']);
            return false;
        }
    }

    /** Get all pending callbacks */
    public function getPending(): \Illuminate\Database\Eloquent\Collection
    {
        return CallbackRequest::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->where('attempts', '<', \DB::raw('max_attempts'))
            ->get();
    }

    /** Cancel a scheduled callback */
    public function cancel(int $id): bool
    {
        return (bool) CallbackRequest::where('id', $id)->update(['status' => 'cancelled']);
    }
}
