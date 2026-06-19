<?php

namespace BitDreamIT\MikoPBX\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Events\IncomingCallEvent;
use BitDreamIT\MikoPBX\Events\CallEndedEvent;
use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Jobs\ScheduleCallbackJob;

/**
 * Webhook Controller
 *
 * Receives real-time events from MikoPBX via HTTP POST webhooks.
 * Configure in MikoPBX:
 *   Admin Panel → Modules → Webhook → URL: https://your-app.com/mikopbx/webhook
 */
class WebhookController
{
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook secret
        if ($secret = config('mikopbx.webhook_secret')) {
            $sig = $request->header('X-MikoPBX-Signature', '');
            if (!hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $sig)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $event = $request->input('event');
        $data  = $request->input('data', []);

        Log::debug("MikoPBX webhook received: $event", $data);

        match ($event) {
            'call.incoming'  => $this->onIncoming($data),
            'call.answered'  => $this->onAnswered($data),
            'call.ended'     => $this->onEnded($data),
            'call.missed'    => $this->onMissed($data),
            'agent.online'   => $this->onAgentOnline($data),
            'agent.offline'  => $this->onAgentOffline($data),
            'dtmf.received'  => $this->onDTMF($data),
            default          => Log::info("MikoPBX unhandled webhook: $event"),
        };

        return response()->json(['status' => 'ok']);
    }

    private function onIncoming(array $data): void
    {
        $log = CallLog::create([
            'caller'     => $data['caller_number'] ?? '',
            'caller_name'=> $data['caller_name']   ?? null,
            'extension'  => $data['extension']     ?? '',
            'channel'    => $data['channel']       ?? '',
            'direction'  => 'inbound',
            'status'     => 'ringing',
            'started_at' => now(),
        ]);

        event(new IncomingCallEvent(
            $data['caller_number'] ?? '',
            $data['extension']     ?? '',
            $data['channel']       ?? '',
            $data['caller_name']   ?? null,
        ));
    }

    private function onAnswered(array $data): void
    {
        CallLog::where('channel', $data['channel'] ?? '')->update([
            'status'      => 'answered',
            'answered_at' => now(),
        ]);
    }

    private function onEnded(array $data): void
    {
        $channel  = $data['channel']   ?? '';
        $cause    = $data['cause']     ?? 'UNKNOWN';
        $duration = (int)($data['duration'] ?? 0);
        $ext      = $data['extension'] ?? '';

        CallLog::where('channel', $channel)->update([
            'status'         => 'ended',
            'cause'          => $cause,
            'duration'       => $duration,
            'recording_file' => $data['recording'] ?? null,
            'ended_at'       => now(),
        ]);

        event(new CallEndedEvent($channel, $cause, $duration, $ext));
    }

    private function onMissed(array $data): void
    {
        CallLog::where('channel', $data['channel'] ?? '')->update(['status' => 'missed']);

        // Auto-schedule callback
        if (!empty($data['caller_number'])) {
            ScheduleCallbackJob::dispatch($data['caller_number'], $data['extension'] ?? '')
                ->delay(now()->addMinutes(config('mikopbx.retry_delay_minutes', 5)));
        }
    }

    private function onAgentOnline(array $data): void
    {
        \BitDreamIT\MikoPBX\Models\Extension::where('number', $data['extension'] ?? '')
            ->update(['online' => true, 'status' => 'REGISTERED', 'last_seen_at' => now()]);
    }

    private function onAgentOffline(array $data): void
    {
        \BitDreamIT\MikoPBX\Models\Extension::where('number', $data['extension'] ?? '')
            ->update(['online' => false, 'status' => 'UNREACHABLE']);
    }

    private function onDTMF(array $data): void
    {
        Log::info("DTMF received", $data);
    }
}
