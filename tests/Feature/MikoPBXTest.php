<?php

use BitDreamIT\MikoPBX\Testing\MikoPBXFake;
use BitDreamIT\MikoPBX\Models\{CallLog, Campaign, CampaignNumber, Extension, Callback, Blacklist};
use BitDreamIT\MikoPBX\Services\{CampaignService, BlacklistService, CallbackService, AnalyticsService};

// ── Call origination ───────────────────────────────────────────────────────
test('can originate a call via facade', function () {
    $fake = MikoPBXFake::make(app());
    $fake->reset();

    app('mikopbx')->originate('101', '01711000000');

    $fake->assertOriginated('101', '01711000000');
    $fake->assertOriginateCount(1);
});

test('fake does not originate when shouldFail is set', function () {
    $fake = MikoPBXFake::make(app());
    $fake->failOnNextCall();

    expect(fn() => app('mikopbx')->originate('101', '01711000000'))
        ->toThrow(\RuntimeException::class);
});

test('assertNothingOriginated passes when no calls made', function () {
    $fake = MikoPBXFake::make(app());
    $fake->reset();
    $fake->assertNothingOriginated();
});

// ── Campaign service ───────────────────────────────────────────────────────
test('campaign is created with correct number count', function () {
    $fake = MikoPBXFake::make(app());

    $numbers = [
        ['number' => '01711000001'],
        ['number' => '01711000002'],
        ['number' => '01711000003'],
    ];

    $svc      = app(CampaignService::class);
    $campaign = $svc->create(['name' => 'Test', 'type' => 'agent_connect'], $numbers);

    expect($campaign)->toBeInstanceOf(Campaign::class)
        ->and($campaign->total_numbers)->toBe(3)
        ->and($campaign->status)->toBe('draft')
        ->and($campaign->numbers()->count())->toBe(3);
});

test('campaign progress is calculated correctly', function () {
    $campaign = Campaign::factory()->create([
        'total_numbers' => 100,
        'dialed'        => 50,
        'answered'      => 40,
        'failed'        => 10,
    ]);

    $svc   = app(CampaignService::class);
    $stats = $svc->getStats($campaign);

    expect($stats['progress'])->toBe(50.0)
        ->and($stats['asr'])->toBe(80.0)
        ->and($stats['pending'])->toBe(50);
});

// ── Blacklist service ──────────────────────────────────────────────────────
test('number is blocked after being added to blacklist', function () {
    $svc = app(BlacklistService::class);
    $svc->add('01711999999', 'Spam caller', 'both');

    expect($svc->isBlocked('01711999999', 'inbound'))->toBeTrue()
        ->and($svc->isBlocked('01711999999', 'outbound'))->toBeTrue();
});

test('number is unblocked after removal', function () {
    $svc = app(BlacklistService::class);
    $svc->add('01711888888', 'Test');
    $svc->remove('01711888888');

    expect($svc->isBlocked('01711888888'))->toBeFalse();
});

test('expired blacklist entry is not blocked', function () {
    Blacklist::create([
        'number'     => '01711777777',
        'direction'  => 'both',
        'expires_at' => now()->subDay(),
    ]);

    $svc = app(BlacklistService::class);
    expect($svc->isBlocked('01711777777'))->toBeFalse();
});

// ── Callback service ───────────────────────────────────────────────────────
test('callback is scheduled with correct defaults', function () {
    $svc = app(CallbackService::class);
    $cb  = $svc->schedule('01711000000', ['name' => 'Test Customer', 'priority' => 'high']);

    expect($cb)->toBeInstanceOf(Callback::class)
        ->and($cb->number)->toBe('01711000000')
        ->and($cb->name)->toBe('Test Customer')
        ->and($cb->priority)->toBe('high')
        ->and($cb->status)->toBe('pending');
});

test('pending callbacks are returned in priority order', function () {
    $svc = app(CallbackService::class);
    $svc->schedule('01711000001', ['priority' => 'low']);
    $svc->schedule('01711000002', ['priority' => 'urgent']);
    $svc->schedule('01711000003', ['priority' => 'normal']);

    $pending = $svc->pending();

    expect($pending->first()->priority)->toBe('urgent');
});

// ── Analytics service ──────────────────────────────────────────────────────
test('analytics summary returns correct counts', function () {
    CallLog::factory()->count(5)->create(['status' => 'answered', 'started_at' => now()]);
    CallLog::factory()->count(3)->create(['status' => 'missed',   'started_at' => now()]);

    $svc    = app(AnalyticsService::class);
    $result = $svc->summary(now()->startOfDay(), now()->endOfDay());

    expect($result['total_calls'])->toBeGreaterThanOrEqual(8)
        ->and($result['answered'])->toBeGreaterThanOrEqual(5)
        ->and($result['missed'])->toBeGreaterThanOrEqual(3);
});

// ── Models ────────────────────────────────────────────────────────────────
test('call log duration formatted works correctly', function () {
    $log = new CallLog(['billsec' => 125]);
    expect($log->duration_formatted)->toBe('02:05');
});

test('campaign progress attribute is correct', function () {
    $c = new Campaign(['total_numbers' => 200, 'dialed' => 150]);
    expect($c->progress)->toBe(75.0);
});

test('extension status dot returns correct tailwind class', function () {
    $ext = new Extension(['status' => 'online']);
    expect($ext->status_dot)->toBe('bg-green-400');

    $ext->status = 'busy';
    expect($ext->status_dot)->toBe('bg-orange-400');

    $ext->status = 'offline';
    expect($ext->status_dot)->toBe('bg-gray-300');
});

// ── Routes ────────────────────────────────────────────────────────────────
test('dashboard route requires authentication', function () {
    $response = $this->get(route('mikopbx.dashboard'));
    $response->assertRedirect('/login');
});

test('authenticated user can access dashboard', function () {
    $user = \App\Models\User::factory()->create();
    $response = $this->actingAs($user)->get(route('mikopbx.dashboard'));
    $response->assertOk();
});
