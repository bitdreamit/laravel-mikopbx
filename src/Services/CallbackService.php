<?php
namespace BitDreamIT\MikoPBX\Services;
use BitDreamIT\MikoPBX\Models\Callback;
use BitDreamIT\MikoPBX\Models\CallLog;

class CallbackService
{
    public function __construct(private RestApiService $api) {}

    public function schedule(string $number, array $opts = []): Callback
    {
        return Callback::create([
            'number'       => $number,
            'name'         => $opts['name'] ?? null,
            'note'         => $opts['note'] ?? null,
            'priority'     => $opts['priority'] ?? 'normal',
            'assigned_to'  => $opts['assigned_to'] ?? null,
            'scheduled_at' => $opts['scheduled_at'] ?? now()->addMinutes(5),
            'call_log_id'  => $opts['call_log_id'] ?? null,
            'created_by'   => auth()->id(),
        ]);
    }

    public function scheduleFromMissedCall(CallLog $log): Callback
    {
        return $this->schedule($log->caller, [
            'name'        => "Missed call — {$log->caller}",
            'call_log_id' => $log->id,
        ]);
    }

    public function attempt(Callback $cb, string $fromExtension): bool
    {
        $cb->update(['status' => 'in_progress', 'attempted_at' => now()]);
        try {
            $this->api->originate($fromExtension, $cb->number);
            $cb->update(['status' => 'completed', 'completed_at' => now()]);
            return true;
        } catch (\Throwable $e) {
            $cb->update(['status' => 'failed']);
            return false;
        }
    }

    public function pending(): \Illuminate\Database\Eloquent\Collection
    {
        return Callback::where('status', 'pending')
            ->where(fn($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->get();
    }
}
