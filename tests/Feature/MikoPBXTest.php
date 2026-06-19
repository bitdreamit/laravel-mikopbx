<?php

use BitDreamIT\MikoPBX\Testing\MikoPBXFake;
use BitDreamIT\MikoPBX\Facades\MikoPBX;
use BitDreamIT\MikoPBX\Events\{IncomingCallEvent, CallEndedEvent, CallMissedEvent};
use BitDreamIT\MikoPBX\Models\{CallLog, Campaign, Blacklist, CallbackRequest};
use BitDreamIT\MikoPBX\Services\{IVRBuilder, AnalyticsService};
use Illuminate\Support\Facades\{Event, Queue};

// ══════════════════════════════════════════════════════════════════
// CALL LOG TESTS
// ══════════════════════════════════════════════════════════════════

describe('CallLog', function () {

    it('creates a call log on incoming call simulation', function () {
        MikoPBXFake::fake();

        MikoPBXFake::simulateIncomingCall('01711XXXXXX', '101', 'PJSIP/101-test001');

        expect(CallLog::where('caller', '01711XXXXXX')->exists())->toBeTrue();
        expect(CallLog::where('extension', '101')->first()->status)->toBe('ringing');
    });

    it('updates call log to answered when bridge event fires', function () {
        MikoPBXFake::fake();

        $log = MikoPBXFake::makeCallLog(['channel' => 'PJSIP/101-test001', 'status' => 'ringing', 'answered_at' => null]);

        CallLog::where('channel', 'PJSIP/101-test001')->update(['status' => 'answered', 'answered_at' => now()]);

        expect(CallLog::where('channel', 'PJSIP/101-test001')->first()->status)->toBe('answered');
    });

    it('marks call as missed on no_answer', function () {
        MikoPBXFake::fake();

        MikoPBXFake::simulateMissedCall('01811XXXXXX', '102');

        expect(CallLog::where('caller', '01811XXXXXX')->where('status', 'missed')->exists())->toBeTrue();
    });

    it('fires IncomingCallEvent when call arrives', function () {
        Event::fake([IncomingCallEvent::class]);

        MikoPBXFake::simulateIncomingCall('01711XXXXXX', '101');

        Event::assertDispatched(IncomingCallEvent::class, fn($e) =>
            $e->callerNumber === '01711XXXXXX' && $e->extension === '101'
        );
    });

    it('fires CallEndedEvent when call ends', function () {
        Event::fake([CallEndedEvent::class]);
        $channel = 'PJSIP/101-test999';
        MikoPBXFake::makeCallLog(['channel' => $channel]);

        MikoPBXFake::simulateCallEnded($channel, 'NORMAL_CLEARING', 120, '101');

        Event::assertDispatched(CallEndedEvent::class, fn($e) => $e->channel === $channel);
    });

    it('calculates correct duration on ended call', function () {
        MikoPBXFake::fake();
        $channel = 'PJSIP/102-test888';

        MikoPBXFake::makeCallLog(['channel' => $channel, 'status' => 'answered']);
        MikoPBXFake::simulateCallEnded($channel, 'NORMAL_CLEARING', 245, '102');

        expect(CallLog::where('channel', $channel)->first()->duration)->toBe(245);
    });

});

// ══════════════════════════════════════════════════════════════════
// BLACKLIST TESTS
// ══════════════════════════════════════════════════════════════════

describe('Blacklist', function () {

    it('blocks a number', function () {
        MikoPBX::blacklist()->block('01911XXXXXX', 'Test spam');

        expect(MikoPBX::blacklist()->isBlocked('01911XXXXXX'))->toBeTrue();
    });

    it('unblocks a number', function () {
        MikoPBX::blacklist()->block('01922XXXXXX', 'Test');
        MikoPBX::blacklist()->unblock('01922XXXXXX');

        expect(MikoPBX::blacklist()->isBlocked('01922XXXXXX'))->toBeFalse();
    });

    it('automatically expires a blocked number', function () {
        MikoPBX::blacklist()->block('01933XXXXXX', 'Temp block', now()->subMinute()->toDateTimeString());

        expect(MikoPBX::blacklist()->isBlocked('01933XXXXXX'))->toBeFalse();
    });

    it('normalizes phone number before blocking', function () {
        MikoPBX::blacklist()->block('+8801700000000', 'Normalize test');

        expect(Blacklist::where('number', '+8801700000000')->exists())->toBeTrue();
    });

    it('cleans expired entries', function () {
        Blacklist::create(['number' => '01944XXXXXX', 'active' => true, 'expires_at' => now()->subDay()]);

        $cleaned = MikoPBX::blacklist()->cleanExpired();
        expect($cleaned)->toBeGreaterThanOrEqual(1);
    });

});

// ══════════════════════════════════════════════════════════════════
// IVR BUILDER TESTS
// ══════════════════════════════════════════════════════════════════

describe('IVRBuilder', function () {

    it('builds a basic IVR menu', function () {
        $ivr = IVRBuilder::make('Main Menu')
            ->greeting('welcome.wav')
            ->timeout(10)
            ->pressToTransfer(1, '101')
            ->pressToTransfer(2, '102')
            ->pressToVoicemail(9)
            ->build();

        expect($ivr)->toHaveKey('name');
        expect($ivr)->toHaveKey('questions');
        expect($ivr['name'])->toBe('Main Menu');
        expect($ivr['questions'][0]['press'])->toHaveCount(3);
    });

    it('builds sales support template', function () {
        $ivr = IVRBuilder::salesSupportTemplate('101', '102', '104');

        expect($ivr)->toBeArray();
        expect($ivr['questions'])->not->toBeEmpty();
    });

    it('respects custom timeout', function () {
        $ivr = IVRBuilder::make('Test')
            ->greeting('test.wav')
            ->timeout(30)
            ->pressToHangup(0)
            ->build();

        expect($ivr['questions'][0]['timeout'])->toBe(30);
    });

    it('builds IVR with all action types', function () {
        $ivr = IVRBuilder::make('Full Menu')
            ->greeting('menu.wav')
            ->pressToTransfer(1, '101')
            ->pressToQueue(2, '200')
            ->pressToVoicemail(3)
            ->pressToHangup(0)
            ->onTimeout('repeat')
            ->onInvalid('repeat')
            ->build();

        $presses = collect($ivr['questions'][0]['press']);

        expect($presses->firstWhere('key', '1')['action'])->toBe('transfer');
        expect($presses->firstWhere('key', '2')['action'])->toBe('queue');
        expect($presses->firstWhere('key', '3')['action'])->toBe('voicemail');
        expect($presses->firstWhere('key', '0')['action'])->toBe('hangup');
    });

});

// ══════════════════════════════════════════════════════════════════
// CALLBACK TESTS
// ══════════════════════════════════════════════════════════════════

describe('Callbacks', function () {

    it('schedules a callback', function () {
        Queue::fake();

        MikoPBX::callback()->schedule('01711XXXXXX', '101', 5);

        Queue::assertPushed(\BitDreamIT\MikoPBX\Jobs\ProcessCallbackJob::class);
        expect(CallbackRequest::where('caller_number', '01711XXXXXX')->exists())->toBeTrue();
    });

    it('cancels a callback', function () {
        Queue::fake();

        $cb = MikoPBX::callback()->schedule('01811XXXXXX', '102', 5);
        MikoPBX::callback()->cancel($cb->id);

        expect(CallbackRequest::find($cb->id)->status)->toBe('cancelled');
    });

    it('returns pending callbacks', function () {
        CallbackRequest::create([
            'caller_number' => '01911XXXXXX',
            'status'        => 'pending',
            'scheduled_at'  => now()->subMinute(),
            'max_attempts'  => 3,
            'attempts'      => 0,
        ]);

        $pending = MikoPBX::callback()->getPending();
        expect($pending)->not->toBeEmpty();
    });

});

// ══════════════════════════════════════════════════════════════════
// ANALYTICS TESTS
// ══════════════════════════════════════════════════════════════════

describe('Analytics', function () {

    beforeEach(function () {
        // Seed some call logs for testing
        CallLog::factory()->count(10)->create([
            'status'     => 'answered',
            'direction'  => 'inbound',
            'duration'   => 120,
            'started_at' => today(),
        ]);
        CallLog::factory()->count(3)->create([
            'status'     => 'missed',
            'direction'  => 'inbound',
            'duration'   => 0,
            'started_at' => today(),
        ]);
    })->skip(fn() => !class_exists('\Database\Factories\CallLogFactory'));

    it('returns correct total call count', function () {
        $stats = MikoPBX::analytics()->dashboard(today()->toDateString(), today()->toDateString());
        expect($stats['total_calls'])->toBeInt();
    });

    it('calculates answer rate correctly', function () {
        $stats = MikoPBX::analytics()->dashboard(today()->toDateString(), today()->toDateString());
        expect($stats['answer_rate'])->toBeFloat()->toBeBetween(0, 100);
    });

    it('exports CSV', function () {
        $csv = MikoPBX::analytics()->exportCsv(today()->toDateString(), today()->toDateString());
        expect($csv)->toContain('ID,Caller,Extension');
    });

});

// ══════════════════════════════════════════════════════════════════
// MIKO PBX FAKE TESTS
// ══════════════════════════════════════════════════════════════════

describe('MikoPBXFake', function () {

    it('creates a call log with makeCallLog helper', function () {
        MikoPBXFake::fake();
        $log = MikoPBXFake::makeCallLog(['extension' => '105', 'caller' => '01700000000']);

        expect($log)->toBeInstanceOf(CallLog::class);
        expect($log->extension)->toBe('105');
    });

    it('creates a missed call log helper', function () {
        MikoPBXFake::fake();
        $log = MikoPBXFake::makeMissedCallLog(['caller' => '01700000001']);

        expect($log->status)->toBe('missed');
        expect($log->duration)->toBe(0);
    });

    it('resets state between tests', function () {
        MikoPBXFake::fake();
        MikoPBXFake::recordOriginate('101', '01700000002');
        MikoPBXFake::assertCallCount(1);

        MikoPBXFake::reset();
        MikoPBXFake::assertNothingOriginated();
    });

});
