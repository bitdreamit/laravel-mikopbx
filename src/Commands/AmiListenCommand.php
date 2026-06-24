<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\AMIService;
use BitDreamIT\MikoPBX\Models\{CallLog, Extension};
use BitDreamIT\MikoPBX\Events\{IncomingCallEvent, CallEndedEvent, CallAnsweredEvent, AgentStatusChangedEvent};
use BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob;

class AmiListenCommand extends Command
{
    protected $signature   = 'mikopbx:listen {--timeout=0 : Stop after N seconds (0 = forever)}';
    protected $description = 'Listen to MikoPBX AMI events in real time (run via Supervisor)';

    public function handle(AMIService $ami): int
    {
        $this->info('🔌 Connecting to MikoPBX AMI...');

        if (! $ami->connect()) {
            $this->error('❌ AMI connection failed. Check MIKOPBX_AMI_* env vars.');
            return 1;
        }

        $this->info('✅ Connected! Listening for events...');

        $startTime = time();
        $timeout   = (int) $this->option('timeout');

        $ami->listen(
            fn(array $event) => $this->dispatch($event),
            fn() => $timeout > 0 && (time() - $startTime) >= $timeout
        );

        $ami->disconnect();
        return 0;
    }

    private function dispatch(array $event): void
    {
        $type = $event['Event'] ?? null;

        match ($type) {
            'Newchannel'  => $this->onNewChannel($event),
            'Bridge'      => $this->onBridge($event),
            'Hangup'      => $this->onHangup($event),
            'PeerStatus'  => $this->onPeerStatus($event),
            'AgentLogin'  => $this->onAgentStatus($event, 'online'),
            'AgentLogoff' => $this->onAgentStatus($event, 'offline'),
            default       => null,
        };
    }

    private function onNewChannel(array $e): void
    {
        $caller    = $e['CallerIDNum'] ?? 'Unknown';
        $extension = $e['Exten'] ?? '';
        $channel   = $e['Channel'] ?? '';
        $direction = str_starts_with($extension, 's') ? 'inbound' : 'outbound';

        $this->line("📞 <info>New call:</info> {$caller} → ext {$extension}");

        $log = CallLog::create([
            'caller'     => $caller,
            'extension'  => $extension,
            'channel'    => $channel,
            'direction'  => $direction,
            'status'     => 'ringing',
            'started_at' => now(),
        ]);

        event(new IncomingCallEvent($log));
    }

    private function onBridge(array $e): void
    {
        $channel = $e['Channel1'] ?? $e['Channel'] ?? '';
        $this->line("✅ <info>Answered:</info> {$channel}");

        CallLog::where('channel', $channel)
            ->update(['status' => 'answered', 'answered_at' => now()]);

        if ($log = CallLog::where('channel', $channel)->first()) {
            event(new CallAnsweredEvent($log));
        }
    }

    private function onHangup(array $e): void
    {
        $channel  = $e['Channel'] ?? '';
        $cause    = $e['Cause-txt'] ?? 'Normal Clearing';
        $duration = (int) ($e['Duration'] ?? 0);

        $this->line("📵 <comment>Ended:</comment> {$channel} | {$cause} | {$duration}s");

        $status = match(true) {
            str_contains($cause, 'NO_USER_RESPONSE'),
            str_contains($cause, 'NO_ANSWER')       => 'missed',
            str_contains($cause, 'USER_BUSY')        => 'busy',
            str_contains($cause, 'UNALLOCATED')      => 'failed',
            default                                  => 'ended',
        };

        CallLog::where('channel', $channel)->update([
            'status'   => $status,
            'cause'    => $cause,
            'duration' => $duration,
            'ended_at' => now(),
        ]);

        if ($log = CallLog::where('channel', $channel)->first()) {
            event(new CallEndedEvent($log));

            // Auto-schedule callback for missed calls
            if ($status === 'missed') {
                ProcessCallbackJob::dispatch($log)->delay(now()->addMinutes(2));
            }
        }
    }

    private function onPeerStatus(array $e): void
    {
        $peer   = $e['Peer'] ?? '';
        $status = $e['PeerStatus'] ?? '';

        $ext = preg_replace('/^PJSIP\/|^SIP\//', '', $peer);

        $newStatus = match($status) {
            'Registered', 'Reachable' => 'online',
            'Unregistered', 'Unreachable' => 'offline',
            default => null,
        };

        if ($newStatus && $ext) {
            $this->line("👤 Agent {$ext} → {$newStatus}");
            Extension::where('extension', $ext)->update([
                'status'       => $newStatus,
                'last_seen_at' => $newStatus === 'online' ? now() : null,
            ]);

            if ($agent = Extension::where('extension', $ext)->first()) {
                event(new AgentStatusChangedEvent($agent, $newStatus));
            }
        }
    }

    private function onAgentStatus(array $e, string $status): void
    {
        $ext = $e['Agent'] ?? $e['Interface'] ?? '';
        $ext = preg_replace('/^PJSIP\/|^SIP\//', '', $ext);
        if ($ext) {
            Extension::where('extension', $ext)->update(['status' => $status]);
        }
    }
}
