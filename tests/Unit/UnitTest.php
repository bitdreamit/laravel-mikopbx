<?php

use BitDreamIT\MikoPBX\Enums\{CallStatus, CampaignStatus, AgentStatus};
use BitDreamIT\MikoPBX\Models\{CallLog, Campaign, Extension, Callback, Blacklist};
use BitDreamIT\MikoPBX\Services\{IVRService, AnalyticsService};
use BitDreamIT\MikoPBX\Testing\MikoPBXFake;

// ── Enum: CallStatus ──────────────────────────────────────────────────────
test('CallStatus answered has correct badge class', function () {
    expect(CallStatus::Answered->badgeClass())->toBe('bg-green-100 text-green-800');
});

test('CallStatus missed has red color', function () {
    expect(CallStatus::Missed->color())->toBe('red');
});

test('all CallStatus cases have labels', function () {
    foreach (CallStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

// ── Enum: CampaignStatus ─────────────────────────────────────────────────
test('CampaignStatus running isActive returns true', function () {
    expect(CampaignStatus::Running->isActive())->toBeTrue();
    expect(CampaignStatus::Paused->isActive())->toBeFalse();
});

test('CampaignStatus draft and paused canStart', function () {
    expect(CampaignStatus::Draft->canStart())->toBeTrue();
    expect(CampaignStatus::Paused->canStart())->toBeTrue();
    expect(CampaignStatus::Running->canStart())->toBeFalse();
    expect(CampaignStatus::Completed->canStart())->toBeFalse();
});

// ── Enum: AgentStatus ────────────────────────────────────────────────────
test('AgentStatus online dot is green', function () {
    expect(AgentStatus::Online->dot())->toBe('bg-green-400');
});

test('AgentStatus online isAvailable returns true', function () {
    expect(AgentStatus::Online->isAvailable())->toBeTrue();
    expect(AgentStatus::Busy->isAvailable())->toBeFalse();
    expect(AgentStatus::Offline->isAvailable())->toBeFalse();
});

// ── Model: CallLog ───────────────────────────────────────────────────────
test('CallLog getTable uses config prefix', function () {
    $log = new CallLog();
    expect($log->getTable())->toContain('call_logs');
});

test('CallLog duration formatted for 0 seconds', function () {
    $log = new CallLog(['billsec' => 0]);
    expect($log->duration_formatted)->toBe('00:00');
});

test('CallLog duration formatted for 3600 seconds', function () {
    $log = new CallLog(['billsec' => 3600]);
    expect($log->duration_formatted)->toBe('60:00');
});

test('CallLog duration formatted for 90 seconds', function () {
    $log = new CallLog(['billsec' => 90]);
    expect($log->duration_formatted)->toBe('01:30');
});

// ── Model: Campaign ──────────────────────────────────────────────────────
test('Campaign progress is 0 when no numbers', function () {
    $c = new Campaign(['total_numbers' => 0, 'dialed' => 0]);
    expect($c->progress)->toBe(0.0);
});

test('Campaign progress is 100 when all dialed', function () {
    $c = new Campaign(['total_numbers' => 50, 'dialed' => 50]);
    expect($c->progress)->toBe(100.0);
});

test('Campaign isRunning returns correct bool', function () {
    $c = new Campaign(['status' => 'running']);
    expect($c->isRunning())->toBeTrue();

    $c->status = 'draft';
    expect($c->isRunning())->toBeFalse();
});

test('Campaign isDone returns true for completed and failed', function () {
    $c = new Campaign(['status' => 'completed']);
    expect($c->isDone())->toBeTrue();

    $c->status = 'failed';
    expect($c->isDone())->toBeTrue();

    $c->status = 'running';
    expect($c->isDone())->toBeFalse();
});

// ── Model: Extension ─────────────────────────────────────────────────────
test('Extension isOnline is true for online and busy', function () {
    $e = new Extension(['status' => 'online']);
    expect($e->is_online)->toBeTrue();

    $e->status = 'busy';
    expect($e->is_online)->toBeTrue();

    $e->status = 'offline';
    expect($e->is_online)->toBeFalse();
});

test('Extension status_color returns correct string', function () {
    expect((new Extension(['status' => 'online']))->status_color)->toBe('green');
    expect((new Extension(['status' => 'dnd']))->status_color)->toBe('red');
    expect((new Extension(['status' => 'offline']))->status_color)->toBe('gray');
});

// ── Model: Callback ──────────────────────────────────────────────────────
test('Callback priority badge for urgent is red', function () {
    $cb = new Callback(['priority' => 'urgent']);
    expect($cb->priority_badge)->toContain('red');
});

test('Callback priority badge for normal is blue', function () {
    $cb = new Callback(['priority' => 'normal']);
    expect($cb->priority_badge)->toContain('blue');
});

// ── Service: IVRService ──────────────────────────────────────────────────
test('IVRService buildTree returns correct structure', function () {
    $svc  = app(IVRService::class);
    $tree = $svc->buildTree('greeting.wav', [
        '1' => ['action' => 'extension', 'target' => '101', 'label' => 'Sales'],
        '2' => ['action' => 'extension', 'target' => '102', 'label' => 'Support'],
        '0' => ['action' => 'extension', 'target' => '100', 'label' => 'Operator'],
    ]);

    expect($tree['greeting'])->toBe('greeting.wav')
        ->and($tree['options'])->toHaveCount(3)
        ->and($tree['options'][0]['digit'])->toBe('1');
});

// ── Testing: MikoPBXFake ─────────────────────────────────────────────────
test('MikoPBXFake records originate calls', function () {
    $fake = MikoPBXFake::make(app());
    $fake->reset();

    app('mikopbx')->originate('101', '01711000001');
    app('mikopbx')->originate('102', '01811000002');

    $fake->assertOriginateCount(2);
    $fake->assertOriginated('101', '01711000001');
    $fake->assertOriginated('102', '01811000002');
});

test('MikoPBXFake reset clears all recorded calls', function () {
    $fake = MikoPBXFake::make(app());
    app('mikopbx')->originate('101', '01711000001');
    $fake->reset();

    $fake->assertNothingOriginated();
    expect($fake->getOriginateCalls())->toBeEmpty();
});

test('MikoPBXFake assertNotOriginated passes when call not made', function () {
    $fake = MikoPBXFake::make(app());
    $fake->reset();

    $fake->assertNotOriginated('101', '99999999');
});

test('MikoPBXFake records transfer calls', function () {
    $fake = MikoPBXFake::make(app());
    $fake->reset();

    app('mikopbx')->transfer('PJSIP/101-0000001', '102');

    $fake->assertTransferred('PJSIP/101-0000001', '102');
});

test('MikoPBXFake records hangup calls', function () {
    $fake = MikoPBXFake::make(app());
    $fake->reset();

    app('mikopbx')->hangup('PJSIP/101-0000001');

    $fake->assertHungUp('PJSIP/101-0000001');
});
