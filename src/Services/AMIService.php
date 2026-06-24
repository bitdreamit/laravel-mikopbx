<?php

namespace BitDreamIT\MikoPBX\Services;

use Illuminate\Support\Facades\Log;
use BitDreamIT\MikoPBX\Exceptions\MikoPBXException;

class AMIService
{
    /** @var resource|false */
    private $socket = false;
    private bool $connected = false;

    public function connect(): bool
    {
        $host    = config('mikopbx.ami.host');
        $port    = config('mikopbx.ami.port', 5038);
        $timeout = config('mikopbx.ami.timeout', 10);

        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (! $this->socket) {
            Log::error("MikoPBX AMI: Cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($this->socket, $timeout);
        fgets($this->socket, 1024); // read welcome banner

        $response = $this->action([
            'Action'   => 'Login',
            'Username' => config('mikopbx.ami.username'),
            'Secret'   => config('mikopbx.ami.secret'),
        ]);

        if (($response['Response'] ?? '') !== 'Success') {
            Log::error('MikoPBX AMI: Login failed', $response);
            fclose($this->socket);
            $this->socket = false;
            return false;
        }

        $this->connected = true;
        return true;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== false;
    }

    /** Send an action and return the response */
    public function action(array $params): array
    {
        if (! $this->socket) {
            throw new MikoPBXException('AMI not connected. Call connect() first.');
        }

        $msg = '';
        foreach ($params as $k => $v) {
            $msg .= "{$k}: {$v}\r\n";
        }
        $msg .= "\r\n";

        fwrite($this->socket, $msg);
        return $this->readPacket();
    }

    /** Read one AMI response packet (ends on blank line) */
    public function readPacket(): array
    {
        $data = [];
        while (($line = fgets($this->socket, 4096)) !== false) {
            $line = trim($line);
            if ($line === '') break;
            if (str_contains($line, ': ')) {
                [$k, $v] = explode(': ', $line, 2);
                $data[trim($k)] = trim($v);
            }
        }
        return $data;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            @fwrite($this->socket, "Action: Logoff\r\n\r\n");
            fclose($this->socket);
            $this->socket = false;
            $this->connected = false;
        }
    }

    // ── High-level actions ───────────────────────────────────────────────────

    public function originate(string $from, string $to, string $context = 'from-internal'): array
    {
        return $this->action([
            'Action'   => 'Originate',
            'Channel'  => "PJSIP/{$from}",
            'Exten'    => $to,
            'Context'  => $context,
            'Priority' => '1',
            'CallerID' => $from,
            'Timeout'  => '30000',
            'Async'    => 'yes',
        ]);
    }

    public function redirect(string $channel, string $extension, string $context = 'from-internal'): array
    {
        return $this->action([
            'Action'   => 'Redirect',
            'Channel'  => $channel,
            'Exten'    => $extension,
            'Context'  => $context,
            'Priority' => '1',
        ]);
    }

    public function hangup(string $channel, int $cause = 16): array
    {
        return $this->action([
            'Action'  => 'Hangup',
            'Channel' => $channel,
            'Cause'   => $cause,
        ]);
    }

    public function mute(string $channel, string $direction = 'both'): array
    {
        return $this->action([
            'Action'    => 'MuteAudio',
            'Channel'   => $channel,
            'Direction' => $direction,
            'State'     => 'on',
        ]);
    }

    public function unmute(string $channel, string $direction = 'both'): array
    {
        return $this->action([
            'Action'    => 'MuteAudio',
            'Channel'   => $channel,
            'Direction' => $direction,
            'State'     => 'off',
        ]);
    }

    public function getChannels(): array
    {
        return $this->action(['Action' => 'CoreShowChannels']);
    }

    public function getStatus(string $channel = ''): array
    {
        $params = ['Action' => 'Status'];
        if ($channel) $params['Channel'] = $channel;
        return $this->action($params);
    }

    public function sipPeers(): array
    {
        return $this->action(['Action' => 'SIPpeers']);
    }

    public function queueStatus(string $queue = ''): array
    {
        $params = ['Action' => 'QueueStatus'];
        if ($queue) $params['Queue'] = $queue;
        return $this->action($params);
    }

    public function queueAdd(string $queue, string $interface, string $penalty = '0'): array
    {
        return $this->action([
            'Action'    => 'QueueAdd',
            'Queue'     => $queue,
            'Interface' => $interface,
            'Penalty'   => $penalty,
        ]);
    }

    public function queueRemove(string $queue, string $interface): array
    {
        return $this->action([
            'Action'    => 'QueueRemove',
            'Queue'     => $queue,
            'Interface' => $interface,
        ]);
    }

    /** Listen for AMI events continuously — used by AmiListenCommand */
    public function listen(callable $handler, callable $shouldStop = null): void
    {
        // Subscribe to all events
        $this->action(['Action' => 'Events', 'EventMask' => 'on']);

        while (true) {
            if (is_callable($shouldStop) && $shouldStop()) break;

            $event = $this->readPacket();
            if (! empty($event)) {
                $handler($event);
            }
        }
    }
}
