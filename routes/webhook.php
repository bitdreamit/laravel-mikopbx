<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use BitDreamIT\MikoPBX\Models\CallLog;
use BitDreamIT\MikoPBX\Events\{IncomingCallEvent, CallEndedEvent};
use BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob;

/**
 * MikoPBX can POST to this URL on call events.
 * Secured by a secret token: MIKOPBX_WEBHOOK_SECRET
 *
 * In MikoPBX admin: System → Webhooks → Add
 *   URL: https://yourapp.com/mikopbx-webhook/call
 *   Method: POST
 */
Route::post('/call', function (Request $request) {
    $secret = config('mikopbx.webhook_secret', '');
    if ($secret && $request->header('X-MikoPBX-Secret') !== $secret) {
        abort(403);
    }

    $event  = $request->input('event');
    $data   = $request->all();

    match ($event) {
        'newcall' => handleNewCall($data),
        'hangup'  => handleHangup($data),
        default   => null,
    };

    return response()->json(['ok' => true]);
});

function handleNewCall(array $d): void
{
    $log = CallLog::create([
        'caller'      => $d['src']       ?? $d['caller'] ?? 'Unknown',
        'extension'   => $d['dst']       ?? $d['extension'] ?? '',
        'channel'     => $d['channel']   ?? '',
        'uniqueid'    => $d['uniqueid']  ?? null,
        'direction'   => 'inbound',
        'status'      => 'ringing',
        'started_at'  => now(),
    ]);
    event(new IncomingCallEvent($log));
}

function handleHangup(array $d): void
{
    $channel = $d['channel'] ?? '';
    $cause   = $d['cause_txt'] ?? $d['cause'] ?? 'Normal Clearing';
    $status  = str_contains(strtoupper($cause), 'NO_ANSWER') ? 'missed' : 'ended';

    $log = CallLog::where('channel', $channel)->first();
    if ($log) {
        $log->update(['status' => $status, 'cause' => $cause, 'ended_at' => now()]);
        event(new CallEndedEvent($log));
        if ($status === 'missed') {
            ProcessCallbackJob::dispatch($log)->delay(now()->addMinutes(2));
        }
    }
}
