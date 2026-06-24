<?php

namespace BitDreamIT\MikoPBX\Testing;

use BitDreamIT\MikoPBX\MikoPBXManager;
use Illuminate\Foundation\Application;

/**
 * MikoPBXFake — use in tests to intercept all MikoPBX calls.
 *
 * Usage in a test:
 *
 *   use BitDreamIT\MikoPBX\Testing\MikoPBXFake;
 *
 *   protected function setUp(): void
 *   {
 *       parent::setUp();
 *       $this->fake = MikoPBXFake::make($this->app);
 *   }
 *
 *   public function test_call_is_originated(): void
 *   {
 *       $this->fake->shouldOriginate('101', '01711000000');
 *       // ... trigger your feature
 *       $this->fake->assertOriginated('101', '01711000000');
 *   }
 */
class MikoPBXFake
{
    private array $originateCalls  = [];
    private array $transferCalls   = [];
    private array $hangupCalls     = [];
    private array $campaignActions = [];
    private bool  $shouldFail      = false;

    private function __construct() {}

    public static function make(Application $app): static
    {
        $fake = new static();

        $app->instance('mikopbx', new class($fake) extends MikoPBXManager {
            public function __construct(private MikoPBXFake $fake)
            {
                // Skip parent constructor
            }

            public function originate(string $from, string $to): array
            {
                $this->fake->recordOriginate($from, $to);
                if ($this->fake->willFail()) throw new \RuntimeException('MikoPBXFake: forced failure');
                return ['success' => true, 'from' => $from, 'to' => $to];
            }

            public function transfer(string $channel, string $to): array
            {
                $this->fake->recordTransfer($channel, $to);
                return ['success' => true];
            }

            public function hangup(string $channel): array
            {
                $this->fake->recordHangup($channel);
                return ['success' => true];
            }

            public function activeCalls(): array
            {
                return ['data' => $this->fake->fakeActiveCalls()];
            }

            public function api(): \BitDreamIT\MikoPBX\Services\RestApiService
            {
                return new FakeRestApiService($this->fake);
            }
        });

        return $fake;
    }

    // ── Recording ────────────────────────────────────────────────────────

    public function recordOriginate(string $from, string $to): void
    {
        $this->originateCalls[] = compact('from', 'to');
    }

    public function recordTransfer(string $channel, string $to): void
    {
        $this->transferCalls[] = compact('channel', 'to');
    }

    public function recordHangup(string $channel): void
    {
        $this->hangupCalls[] = compact('channel');
    }

    public function recordCampaignAction(string $action, array $data = []): void
    {
        $this->campaignActions[] = compact('action', 'data');
    }

    // ── Configuration ────────────────────────────────────────────────────

    public function failOnNextCall(): static
    {
        $this->shouldFail = true;
        return $this;
    }

    public function willFail(): bool
    {
        $fail = $this->shouldFail;
        $this->shouldFail = false;
        return $fail;
    }

    public function fakeActiveCalls(array $calls = []): array
    {
        return $calls;
    }

    // ── Assertions ───────────────────────────────────────────────────────

    public function assertOriginated(string $from, string $to): void
    {
        $match = collect($this->originateCalls)->first(
            fn($c) => $c['from'] === $from && $c['to'] === $to
        );
        \PHPUnit\Framework\Assert::assertNotNull(
            $match,
            "Expected originate call from [{$from}] to [{$to}] was not made.\nActual calls: " . json_encode($this->originateCalls)
        );
    }

    public function assertNotOriginated(string $from, string $to): void
    {
        $match = collect($this->originateCalls)->first(
            fn($c) => $c['from'] === $from && $c['to'] === $to
        );
        \PHPUnit\Framework\Assert::assertNull(
            $match,
            "Unexpected originate call from [{$from}] to [{$to}] was made."
        );
    }

    public function assertOriginateCount(int $count): void
    {
        \PHPUnit\Framework\Assert::assertCount($count, $this->originateCalls);
    }

    public function assertTransferred(string $channel, string $to): void
    {
        $match = collect($this->transferCalls)->first(
            fn($c) => $c['channel'] === $channel && $c['to'] === $to
        );
        \PHPUnit\Framework\Assert::assertNotNull($match,
            "Expected transfer of [{$channel}] to [{$to}] was not made."
        );
    }

    public function assertHungUp(string $channel): void
    {
        $match = collect($this->hangupCalls)->first(fn($c) => $c['channel'] === $channel);
        \PHPUnit\Framework\Assert::assertNotNull($match,
            "Expected hangup of [{$channel}] was not made."
        );
    }

    public function assertNothingOriginated(): void
    {
        \PHPUnit\Framework\Assert::assertEmpty($this->originateCalls, 'No calls should have been originated.');
    }

    public function assertCampaignStarted(): void
    {
        $started = collect($this->campaignActions)->where('action', 'start')->isNotEmpty();
        \PHPUnit\Framework\Assert::assertTrue($started, 'Expected a campaign to be started.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function getOriginateCalls(): array { return $this->originateCalls; }
    public function getTransferCalls(): array  { return $this->transferCalls; }
    public function getHangupCalls(): array    { return $this->hangupCalls; }

    public function reset(): void
    {
        $this->originateCalls  = [];
        $this->transferCalls   = [];
        $this->hangupCalls     = [];
        $this->campaignActions = [];
        $this->shouldFail      = false;
    }
}

/**
 * Fake RestApiService used internally by MikoPBXFake.
 */
class FakeRestApiService extends \BitDreamIT\MikoPBX\Services\RestApiService
{
    public function __construct(private MikoPBXFake $fake)
    {
        // Skip parent constructor — no HTTP needed
    }

    public function originate(string $from, string $to, array $opts = []): array
    {
        $this->fake->recordOriginate($from, $to);
        return ['success' => true];
    }

    public function transfer(string $channel, string $to, string $context = 'from-internal'): array
    {
        $this->fake->recordTransfer($channel, $to);
        return ['success' => true];
    }

    public function hangup(string $channel): array
    {
        $this->fake->recordHangup($channel);
        return ['success' => true];
    }

    public function getActiveCalls(): array    { return ['data' => []]; }
    public function getExtensions(): array     { return ['data' => []]; }
    public function getCDR(string $f, string $t, array $filters = []): array { return ['data' => []]; }
    public function getExtensionStatuses(): array { return ['data' => []]; }
    public function createDialerTask(array $d): array { $this->fake->recordCampaignAction('create', $d); return ['id' => 1]; }
    public function startDialerTask(int $id): array   { $this->fake->recordCampaignAction('start', ['id' => $id]); return ['success' => true]; }
    public function stopDialerTask(int $id): array    { $this->fake->recordCampaignAction('stop',  ['id' => $id]); return ['success' => true]; }
    public function pauseDialerTask(int $id): array   { $this->fake->recordCampaignAction('pause', ['id' => $id]); return ['success' => true]; }
    public function getSystemInfo(): array { return ['data' => ['version' => 'fake']]; }
    public function getTrunkStatus(): array { return ['data' => [['state' => 'Registered']]]; }
}
