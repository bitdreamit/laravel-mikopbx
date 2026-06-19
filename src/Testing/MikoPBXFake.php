<?php

namespace BitDreamIT\MikoPBX\Testing;

use BitDreamIT\MikoPBX\Events\IncomingCallEvent;
use BitDreamIT\MikoPBX\Events\CallEndedEvent;
use BitDreamIT\MikoPBX\Models\CallLog;
use Illuminate\Support\Facades\Event;

/**
 * MikoPBXFake — Test helper for unit tests
 *
 * Usage in tests:
 *   MikoPBXFake::fake();
 *   MikoPBX::call()->originate('101', '01711XXXXXX');
 *   MikoPBXFake::assertOriginated('101', '01711XXXXXX');
 *   MikoPBXFake::assertCallCount(1);
 */
class MikoPBXFake
{
    private static array $originatedCalls = [];
    private static array $transferredCalls = [];
    private static array $hungupChannels = [];
    private static array $campaigns = [];

    public static function fake(): void
    {
        static::reset();
        Event::fake([IncomingCallEvent::class, CallEndedEvent::class]);
    }

    public static function reset(): void
    {
        static::$originatedCalls  = [];
        static::$transferredCalls = [];
        static::$hungupChannels   = [];
        static::$campaigns        = [];
    }

    // ─────────────────────────────────────────
    // SIMULATE EVENTS
    // ─────────────────────────────────────────

    /** Simulate an incoming call arriving */
    public static function simulateIncomingCall(string $caller, string $extension, string $channel = ''): void
    {
        $channel = $channel ?: 'PJSIP/' . $extension . '-' . uniqid();

        CallLog::create([
            'caller'     => $caller,
            'extension'  => $extension,
            'channel'    => $channel,
            'direction'  => 'inbound',
            'status'     => 'ringing',
            'started_at' => now(),
        ]);

        event(new IncomingCallEvent($caller, $extension, $channel));
    }

    /** Simulate a call ending */
    public static function simulateCallEnded(string $channel, string $cause = 'NORMAL_CLEARING', int $duration = 60, string $extension = ''): void
    {
        CallLog::where('channel', $channel)->update([
            'status'   => 'ended',
            'cause'    => $cause,
            'duration' => $duration,
            'ended_at' => now(),
        ]);

        event(new CallEndedEvent($channel, $cause, $duration, $extension));
    }

    /** Simulate a missed call */
    public static function simulateMissedCall(string $caller, string $extension): void
    {
        static::simulateIncomingCall($caller, $extension);
        $channel = 'PJSIP/' . $extension . '-' . uniqid();
        static::simulateCallEnded($channel, 'NO_ANSWER', 0, $extension);
    }

    // ─────────────────────────────────────────
    // RECORD & ASSERT
    // ─────────────────────────────────────────

    public static function recordOriginate(string $from, string $to): void
    {
        static::$originatedCalls[] = ['from' => $from, 'to' => $to, 'at' => now()];
    }

    public static function recordTransfer(string $channel, string $extension): void
    {
        static::$transferredCalls[] = ['channel' => $channel, 'extension' => $extension, 'at' => now()];
    }

    public static function recordHangup(string $channel): void
    {
        static::$hungupChannels[] = ['channel' => $channel, 'at' => now()];
    }

    public static function assertOriginated(string $from, string $to): void
    {
        $found = collect(static::$originatedCalls)->first(fn($c) => $c['from'] === $from && $c['to'] === $to);
        assert($found !== null, "Expected call from {$from} to {$to} was not originated.");
    }

    public static function assertNotOriginated(string $from, string $to): void
    {
        $found = collect(static::$originatedCalls)->first(fn($c) => $c['from'] === $from && $c['to'] === $to);
        assert($found === null, "Call from {$from} to {$to} was originated but was not expected.");
    }

    public static function assertCallCount(int $expected): void
    {
        $actual = count(static::$originatedCalls);
        assert($actual === $expected, "Expected {$expected} calls originated, got {$actual}.");
    }

    public static function assertTransferred(string $channel, string $extension): void
    {
        $found = collect(static::$transferredCalls)->first(fn($c) => $c['channel'] === $channel && $c['extension'] === $extension);
        assert($found !== null, "Expected transfer of {$channel} to {$extension} did not happen.");
    }

    public static function assertHungUp(string $channel): void
    {
        $found = collect(static::$hungupChannels)->first(fn($c) => $c['channel'] === $channel);
        assert($found !== null, "Expected hangup of {$channel} did not happen.");
    }

    public static function assertNothingOriginated(): void
    {
        assert(empty(static::$originatedCalls), 'Expected no calls to be originated, but ' . count(static::$originatedCalls) . ' were.');
    }

    public static function assertIncomingCallFired(string $caller, string $extension): void
    {
        Event::assertDispatched(IncomingCallEvent::class, fn($e) => $e->callerNumber === $caller && $e->extension === $extension);
    }

    public static function assertCallEndedFired(string $channel): void
    {
        Event::assertDispatched(CallEndedEvent::class, fn($e) => $e->channel === $channel);
    }

    // ─────────────────────────────────────────
    // FACTORIES / HELPERS
    // ─────────────────────────────────────────

    public static function makeCallLog(array $overrides = []): CallLog
    {
        return CallLog::create(array_merge([
            'caller'     => '017' . rand(10000000, 99999999),
            'extension'  => '10' . rand(1, 9),
            'channel'    => 'PJSIP/101-' . uniqid(),
            'direction'  => 'inbound',
            'status'     => 'answered',
            'duration'   => rand(30, 300),
            'started_at' => now()->subMinutes(rand(1, 60)),
            'ended_at'   => now(),
        ], $overrides));
    }

    public static function makeMissedCallLog(array $overrides = []): CallLog
    {
        return static::makeCallLog(array_merge(['status' => 'missed', 'duration' => 0, 'ended_at' => null], $overrides));
    }
}
