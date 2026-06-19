<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Exceptions\MikoPBXException;

class AMIService
{
    private mixed $socket = null;
    private bool  $connected = false;
    private array $listeners = [];
    private int   $actionId  = 0;

    public function __construct(private array $config) {}

    public function connect(): static
    {
        $this->socket = @fsockopen($this->config['ami_host'], $this->config['ami_port'], $errno, $errstr, 10);
        if (!$this->socket) throw new MikoPBXException("AMI connection failed: $errstr ($errno)");
        stream_set_timeout($this->socket, 5);
        fgets($this->socket, 1024);
        $this->sendRaw(['Action' => 'Login', 'Username' => $this->config['ami_username'], 'Secret' => $this->config['ami_secret']]);
        $r = $this->readPacket();
        if (($r['Response'] ?? '') !== 'Success') throw new MikoPBXException('AMI login failed: ' . ($r['Message'] ?? 'unknown'));
        $this->connected = true;
        return $this;
    }

    public function disconnect(): void
    {
        if ($this->socket) { $this->sendRaw(['Action' => 'Logoff']); fclose($this->socket); $this->socket = null; $this->connected = false; }
    }

    public function isConnected(): bool { return $this->connected && $this->socket !== null; }
    public function reconnect(): static { $this->disconnect(); return $this->connect(); }
    private function nextId(): string { return 'lv-' . (++$this->actionId) . '-' . time(); }

    private function sendRaw(array $data): void
    {
        if (!$this->socket) throw new MikoPBXException('AMI not connected');
        fwrite($this->socket, collect($data)->map(fn($v, $k) => "$k: $v")->implode("\r\n") . "\r\n\r\n");
    }

    public function readPacket(): array
    {
        $p = [];
        while ($line = fgets($this->socket, 4096)) {
            $line = trim($line);
            if ($line === '') break;
            if (str_contains($line, ': ')) { [$k, $v] = explode(': ', $line, 2); $p[trim($k)] = trim($v); }
        }
        return $p;
    }

    private function action(array $data): array
    {
        $data['ActionID'] = $this->nextId();
        $this->sendRaw($data);
        return $this->readPacket();
    }

    // ── CALL CONTROL ──────────────────────────────────────────────
    public function originate(string $channel, string $extension, string $context = 'from-internal', string $callerId = '', int $timeout = 30000, array $vars = []): array
    {
        $d = ['Action' => 'Originate', 'Channel' => $channel, 'Exten' => $extension, 'Context' => $context, 'Priority' => 1, 'Timeout' => $timeout, 'Async' => 'yes'];
        if ($callerId) $d['CallerID'] = $callerId;
        foreach ($vars as $k => $v) $d["Variable"] = "$k=$v";
        return $this->action($d);
    }

    public function blindTransfer(string $channel, string $ext, string $ctx = 'from-internal'): array
    {
        return $this->action(['Action' => 'Redirect', 'Channel' => $channel, 'Exten' => $ext, 'Context' => $ctx, 'Priority' => 1]);
    }

    public function attendedTransfer(string $ch1, string $ch2): array
    {
        return $this->action(['Action' => 'Bridge', 'Channel1' => $ch1, 'Channel2' => $ch2, 'Tone' => 'no']);
    }

    public function hangup(string $channel, int $cause = 16): array
    {
        return $this->action(['Action' => 'Hangup', 'Channel' => $channel, 'Cause' => $cause]);
    }

    public function setVar(string $channel, string $var, string $val): array
    {
        return $this->action(['Action' => 'Setvar', 'Channel' => $channel, 'Variable' => $var, 'Value' => $val]);
    }

    public function getVar(string $channel, string $var): array
    {
        return $this->action(['Action' => 'Getvar', 'Channel' => $channel, 'Variable' => $var]);
    }

    public function sendDTMF(string $channel, string $digit): array
    {
        return $this->action(['Action' => 'PlayDTMF', 'Channel' => $channel, 'Digit' => $digit]);
    }

    public function mute(string $channel, string $direction = 'in'): array
    {
        return $this->action(['Action' => 'MuteAudio', 'Channel' => $channel, 'Direction' => $direction, 'State' => 'on']);
    }

    public function unmute(string $channel, string $direction = 'in'): array
    {
        return $this->action(['Action' => 'MuteAudio', 'Channel' => $channel, 'Direction' => $direction, 'State' => 'off']);
    }

    public function getChannelStatus(?string $channel = null): array
    {
        $d = ['Action' => 'Status'];
        if ($channel) $d['Channel'] = $channel;
        return $this->action($d);
    }

    // ── QUEUE MANAGEMENT ──────────────────────────────────────────
    public function queueAdd(string $queue, string $interface, string $memberName = '', bool $paused = false): array
    {
        return $this->action(['Action' => 'QueueAdd', 'Queue' => $queue, 'Interface' => $interface, 'MemberName' => $memberName ?: $interface, 'Paused' => $paused ? '1' : '0']);
    }

    public function queueRemove(string $queue, string $interface): array
    {
        return $this->action(['Action' => 'QueueRemove', 'Queue' => $queue, 'Interface' => $interface]);
    }

    public function queuePause(string $queue, string $interface, string $reason = ''): array
    {
        return $this->action(['Action' => 'QueuePause', 'Queue' => $queue, 'Interface' => $interface, 'Paused' => 'true', 'Reason' => $reason]);
    }

    public function queueUnpause(string $queue, string $interface): array
    {
        return $this->action(['Action' => 'QueuePause', 'Queue' => $queue, 'Interface' => $interface, 'Paused' => 'false']);
    }

    public function queueStatus(?string $queue = null): array
    {
        $d = ['Action' => 'QueueStatus'];
        if ($queue) $d['Queue'] = $queue;
        return $this->action($d);
    }

    public function queueSummary(?string $queue = null): array
    {
        $d = ['Action' => 'QueueSummary'];
        if ($queue) $d['Queue'] = $queue;
        return $this->action($d);
    }

    // ── VOICEMAIL ─────────────────────────────────────────────────
    public function getVoicemailCount(string $mailbox): array { return $this->action(['Action' => 'MailboxCount', 'Mailbox' => $mailbox]); }
    public function mailboxStatus(string $mailbox): array     { return $this->action(['Action' => 'MailboxStatus', 'Mailbox' => $mailbox]); }
    public function listVoicemailUsers(): array               { return $this->action(['Action' => 'VoicemailUsersList']); }

    // ── CALL PARKING ──────────────────────────────────────────────
    public function parkCall(string $channel, string $channel2, string $lot = ''): array
    {
        $d = ['Action' => 'Park', 'Channel' => $channel, 'Channel2' => $channel2, 'Timeout' => 45];
        if ($lot) $d['Parkinglot'] = $lot;
        return $this->action($d);
    }

    public function getParkedCalls(?string $lot = null): array
    {
        $d = ['Action' => 'ParkedCalls'];
        if ($lot) $d['ParkingLot'] = $lot;
        return $this->action($d);
    }

    // ── MONITORING & SPY ──────────────────────────────────────────
    public function monitorStart(string $channel, string $file, bool $mix = true): array
    {
        return $this->action(['Action' => 'Monitor', 'Channel' => $channel, 'File' => $file, 'Format' => 'wav', 'Mix' => $mix ? '1' : '0']);
    }

    public function monitorStop(string $channel): array   { return $this->action(['Action' => 'StopMonitor', 'Channel' => $channel]); }
    public function monitorPause(string $channel): array  { return $this->action(['Action' => 'PauseMonitor', 'Channel' => $channel]); }
    public function monitorResume(string $channel): array { return $this->action(['Action' => 'UnpauseMonitor', 'Channel' => $channel]); }

    // ── CONFBRIDGE ────────────────────────────────────────────────
    public function confbridgeList(?string $conf = null): array
    {
        $d = ['Action' => 'ConfbridgeList'];
        if ($conf) $d['Conference'] = $conf;
        return $this->action($d);
    }

    public function confbridgeMute(string $conf, string $ch): array   { return $this->action(['Action' => 'ConfbridgeMute',   'Conference' => $conf, 'Channel' => $ch]); }
    public function confbridgeUnmute(string $conf, string $ch): array { return $this->action(['Action' => 'ConfbridgeUnmute', 'Conference' => $conf, 'Channel' => $ch]); }
    public function confbridgeKick(string $conf, string $ch): array   { return $this->action(['Action' => 'ConfbridgeKick',   'Conference' => $conf, 'Channel' => $ch]); }
    public function confbridgeLock(string $conf): array               { return $this->action(['Action' => 'ConfbridgeLock',   'Conference' => $conf]); }
    public function confbridgeUnlock(string $conf): array             { return $this->action(['Action' => 'ConfbridgeUnlock', 'Conference' => $conf]); }

    // ── ASTDB ─────────────────────────────────────────────────────
    public function dbGet(string $family, string $key): array              { return $this->action(['Action' => 'DBGet',     'Family' => $family, 'Key' => $key]); }
    public function dbPut(string $family, string $key, string $val): array { return $this->action(['Action' => 'DBPut',     'Family' => $family, 'Key' => $key, 'Val' => $val]); }
    public function dbDelete(string $family, string $key): array           { return $this->action(['Action' => 'DBDel',     'Family' => $family, 'Key' => $key]); }
    public function dbDeleteTree(string $family): array                    { return $this->action(['Action' => 'DBDelTree',  'Family' => $family]); }

    // ── SIP / PJSIP ───────────────────────────────────────────────
    public function getSIPPeers(): array                     { return $this->action(['Action' => 'SIPpeers']); }
    public function getSIPPeer(string $peer): array          { return $this->action(['Action' => 'SIPshowpeer', 'Peer' => $peer]); }
    public function getPJSIPEndpoints(): array               { return $this->action(['Action' => 'PJSIPShowEndpoints']); }
    public function getPJSIPEndpoint(string $ep): array      { return $this->action(['Action' => 'PJSIPShowEndpoint', 'Endpoint' => $ep]); }

    // ── SYSTEM ────────────────────────────────────────────────────
    public function reloadDialplan(): array               { return $this->action(['Action' => 'Command', 'Command' => 'dialplan reload']); }
    public function moduleReload(string $mod): array      { return $this->action(['Action' => 'ModuleReload', 'Module' => $mod]); }
    public function moduleLoad(string $mod): array        { return $this->action(['Action' => 'ModuleLoad', 'Module' => $mod]); }
    public function moduleUnload(string $mod): array      { return $this->action(['Action' => 'ModuleUnload', 'Module' => $mod]); }
    public function command(string $cmd): array           { return $this->action(['Action' => 'Command', 'Command' => $cmd]); }
    public function getUptime(): array                    { return $this->action(['Action' => 'CoreStatus']); }
    public function ping(): bool                          { return ($this->action(['Action' => 'Ping'])['Response'] ?? '') === 'Success'; }

    // ── EVENT LOOP ────────────────────────────────────────────────
    public function on(string $event, callable $cb): static { $this->listeners[$event][] = $cb; return $this; }

    public function listen(): void
    {
        if (!$this->isConnected()) $this->connect();
        while (true) {
            $p = $this->readPacket();
            if (empty($p)) { usleep(50000); continue; }
            $name = $p['Event'] ?? null;
            if ($name && isset($this->listeners[$name])) {
                foreach ($this->listeners[$name] as $cb) {
                    try { $cb($p); } catch (\Throwable $e) { Log::error("AMI[$name]: " . $e->getMessage()); }
                }
            }
            foreach ($this->listeners['*'] ?? [] as $cb) { $cb($p); }
        }
    }
}
