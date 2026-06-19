<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use BitDreamIT\MikoPBX\Services\AMIService;
use BitDreamIT\MikoPBX\Services\BlacklistService;
use BitDreamIT\MikoPBX\Services\SmsNotificationService;
use BitDreamIT\MikoPBX\Events\IncomingCallEvent;
use BitDreamIT\MikoPBX\Events\CallEndedEvent;
use BitDreamIT\MikoPBX\Models\{CallLog, Extension, CallbackRequest};
use BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob;

class AmiListenCommand extends Command
{
    protected $signature   = 'mikopbx:listen {--verbose : Show raw events} {--no-reconnect : Disable auto-reconnect}';
    protected $description = 'Listen to MikoPBX AMI real-time events (run via Supervisor in production)';
    private int $reconnects = 0;

    public function handle(AMIService $ami, BlacklistService $bl, SmsNotificationService $sms): int
    {
        $this->info('bitdreamit/laravel-mikopbx — AMI Listener');
        $this->newLine();
        return $this->startListening($ami, $bl, $sms);
    }

    private function startListening(AMIService $ami, BlacklistService $bl, SmsNotificationService $sms): int
    {
        try {
            $ami->connect();
            $this->reconnects = 0;
            $this->info('Connected to MikoPBX AMI — listening for events...');
        } catch (\Throwable $e) {
            $this->error('AMI connection failed: ' . $e->getMessage());
            return $this->option('no-reconnect') ? self::FAILURE : $this->reconnect($ami, $bl, $sms);
        }

        // Incoming call
        $ami->on('Newchannel', function (array $ev) use ($bl) {
            $caller = $ev['CallerIDNum'] ?? ''; $ext = $ev['Exten'] ?? ''; $ch = $ev['Channel'] ?? '';
            if (!$ext || in_array($ext, ['s','h','i','t'])) return;
            if ($bl->isBlocked($caller)) { $this->warn("BLOCKED  {$caller}"); return; }
            $this->line("RINGING  {$caller} -> ext {$ext}");
            CallLog::create(['caller' => $caller, 'extension' => $ext, 'channel' => $ch, 'direction' => 'inbound', 'status' => 'ringing', 'started_at' => now()]);
            event(new IncomingCallEvent($caller, $ext, $ch));
        });

        // Outbound dial
        $ami->on('DialBegin', function (array $ev) {
            $src = $ev['CallerIDNum'] ?? ''; $dst = $ev['DestCallerIDNum'] ?? ''; $ch = $ev['Channel'] ?? '';
            if ($src && $dst) { $this->line("DIALING  {$src} -> {$dst}"); }
        });

        // Answered
        $ami->on('Bridge', function (array $ev) {
            $ch1 = $ev['Channel1'] ?? ''; $ch2 = $ev['Channel2'] ?? '';
            $this->line("ANSWERED {$ch1} <-> {$ch2}");
            foreach ([$ch1, $ch2] as $ch) {
                if ($ch) CallLog::where('channel', $ch)->whereNull('answered_at')->update(['status' => 'answered', 'answered_at' => now()]);
            }
        });

        // Ended
        $ami->on('Hangup', function (array $ev) use ($sms) {
            $ch = $ev['Channel'] ?? ''; $cause = $ev['Cause-txt'] ?? 'UNKNOWN'; $dur = (int)($ev['Duration'] ?? 0);
            $ext = $this->extractExt($ch);
            $this->line("ENDED    {$ch} | {$dur}s | {$cause}");
            $log = CallLog::where('channel', $ch)->latest()->first();
            CallLog::where('channel', $ch)->update(['status' => 'ended', 'cause' => $cause, 'duration' => $dur, 'ended_at' => now()]);
            if (str_contains($cause, 'NO_ANSWER') || str_contains($cause, 'NO_USER_RESPONSE')) {
                CallLog::where('channel', $ch)->update(['status' => 'missed']);
                if ($log?->caller) {
                    $cb = CallbackRequest::create(['caller_number' => $log->caller, 'extension' => $ext, 'reason' => 'missed_call', 'status' => 'pending', 'scheduled_at' => now()->addMinutes(5), 'max_attempts' => 3]);
                    ProcessCallbackJob::dispatch($cb)->delay(now()->addMinutes(5));
                    $this->warn("Callback scheduled for {$log->caller}");
                }
            }
            event(new CallEndedEvent($ch, $cause, $dur, $ext));
        });

        // Agent status
        $ami->on('PeerStatus', function (array $ev) {
            $peer = $ev['Peer'] ?? ''; $status = $ev['PeerStatus'] ?? '';
            $num  = last(explode('/', $peer));
            $this->line("AGENT    {$peer} -> {$status}");
            Extension::where('number', $num)->orWhere('sip_peer', $peer)->update(['online' => in_array($status, ['Registered','Reachable']), 'status' => strtoupper($status), 'last_seen_at' => now()]);
        });

        // Queue events
        $ami->on('QueueCallerJoin',  fn($ev) => $this->line("QUEUE    {$ev['CallerIDNum']} joined {$ev['Queue']} pos#{$ev['Position']}"));
        $ami->on('QueueCallerLeave', fn($ev) => $this->line("QUEUE    {$ev['CallerIDNum']} left {$ev['Queue']}"));
        $ami->on('AgentCalled',      fn($ev) => $this->line("RINGING  agent {$ev['AgentName']} for {$ev['CallerIDNum']}"));
        $ami->on('AgentConnect',     fn($ev) => $this->line("ANSWERED agent {$ev['AgentName']} took {$ev['CallerIDNum']}"));

        // Voicemail
        $ami->on('MessageWaiting', function (array $ev) {
            if (($ev['Waiting'] ?? '0') !== '0') $this->line("VOICEMAIL {$ev['Mailbox']} has {$ev['Waiting']} new message(s)");
        });

        // Conference
        $ami->on('ConfbridgeJoin',  fn($ev) => $this->line("CONF     {$ev['CallerIDNum']} joined {$ev['Conference']}"));
        $ami->on('ConfbridgeLeave', fn($ev) => $this->line("CONF     {$ev['CallerIDNum']} left {$ev['Conference']}"));

        // Parking
        $ami->on('ParkedCall',   fn($ev) => $this->line("PARKED   {$ev['CallerIDNum']} -> space {$ev['ParkingSpace']}"));
        $ami->on('UnParkedCall', fn($ev) => $this->line("UNPARKED space {$ev['ParkingSpace']} retrieved"));

        // Transfer
        $ami->on('Transfer', fn($ev) => $this->line("TRANSFER {$ev['TransferType']} -> ext {$ev['TransferExten']}"));

        // Reload
        $ami->on('Reload', fn($ev) => $this->warn("RELOAD   Asterisk reloaded {$ev['Module']}"));

        // Verbose raw
        if ($this->option('verbose')) {
            $ami->on('*', fn($ev) => $this->line('[RAW] ' . ($ev['Event'] ?? '?') . ': ' . json_encode($ev)));
        }

        try {
            $ami->listen();
        } catch (\Throwable $e) {
            $this->error('AMI lost: ' . $e->getMessage());
            if (!$this->option('no-reconnect')) return $this->reconnect($ami, $bl, $sms);
        }

        return self::SUCCESS;
    }

    private function reconnect(AMIService $ami, BlacklistService $bl, SmsNotificationService $sms): int
    {
        if (++$this->reconnects > 10) { $this->error('Max reconnects reached.'); return self::FAILURE; }
        $wait = min(30, $this->reconnects * 5);
        $this->warn("Reconnecting in {$wait}s (attempt {$this->reconnects}/10)...");
        sleep($wait);
        return $this->startListening($ami, $bl, $sms);
    }

    private function extractExt(string $ch): string
    {
        if (preg_match('/PJSIP\/(\d+)-/', $ch, $m)) return $m[1];
        if (preg_match('/SIP\/(\d+)-/',   $ch, $m)) return $m[1];
        return '';
    }
}
