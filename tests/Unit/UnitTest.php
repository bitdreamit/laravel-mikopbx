<?php

use BitDreamIT\MikoPBX\Services\IVRBuilder;
use BitDreamIT\MikoPBX\Services\BlacklistService;
use BitDreamIT\MikoPBX\Traits\{FormatsCallDuration, ValidatesPhoneNumber};
use BitDreamIT\MikoPBX\DTOs\{CallDTO, OriginateDTO, CampaignDTO};
use BitDreamIT\MikoPBX\Enums\{CallStatus, CallDirection, HangupCause, AgentStatus};

// ══════════════════════════════════════════════════════════════════
// IVR BUILDER UNIT TESTS
// ══════════════════════════════════════════════════════════════════

describe('IVRBuilder Unit', function () {

    it('has correct name', function () {
        $ivr = IVRBuilder::make('Test IVR')->greeting('test.wav')->pressToHangup(0)->build();
        expect($ivr['name'])->toBe('Test IVR');
    });

    it('adds multiple keypresses', function () {
        $ivr = IVRBuilder::make('Test')
            ->greeting('test.wav')
            ->pressToTransfer(1, '101')
            ->pressToTransfer(2, '102')
            ->pressToTransfer(3, '103')
            ->build();
        expect($ivr['questions'][0]['press'])->toHaveCount(3);
    });

    it('sets correct timeout', function () {
        $ivr = IVRBuilder::make('Test')->greeting('test.wav')->timeout(15)->pressToHangup(0)->build();
        expect($ivr['questions'][0]['timeout'])->toBe(15);
    });

    it('sets timeout action', function () {
        $ivr = IVRBuilder::make('Test')->greeting('test.wav')->onTimeout('hangup')->pressToHangup(0)->build();
        expect($ivr['questions'][0]['onTimeout'])->toBe('hangup');
    });

    it('builds survey template', function () {
        $ivr = IVRBuilder::surveyTemplate('103');
        expect($ivr)->toHaveKey('questions');
        expect($ivr['questions'])->not->toBeEmpty();
    });

});

// ══════════════════════════════════════════════════════════════════
// ENUMS UNIT TESTS
// ══════════════════════════════════════════════════════════════════

describe('Enums', function () {

    it('CallStatus has correct label', function () {
        expect(CallStatus::Answered->label())->toContain('Answered');
        expect(CallStatus::Missed->label())->toContain('Missed');
    });

    it('CallStatus has correct color', function () {
        expect(CallStatus::Answered->color())->toBe('green');
        expect(CallStatus::Missed->color())->toBe('red');
    });

    it('HangupCause identifies missed correctly', function () {
        expect(HangupCause::NoAnswer->isMissed())->toBeTrue();
        expect(HangupCause::Normal->isMissed())->toBeFalse();
    });

    it('HangupCause identifies busy', function () {
        expect(HangupCause::Busy->isBusy())->toBeTrue();
        expect(HangupCause::Normal->isBusy())->toBeFalse();
    });

    it('HangupCause requiresCallback for missed and busy', function () {
        expect(HangupCause::NoAnswer->requiresCallback())->toBeTrue();
        expect(HangupCause::Busy->requiresCallback())->toBeTrue();
        expect(HangupCause::Normal->requiresCallback())->toBeFalse();
    });

    it('AgentStatus identifies available', function () {
        expect(AgentStatus::Online->isAvailable())->toBeTrue();
        expect(AgentStatus::Busy->isAvailable())->toBeFalse();
    });

    it('AgentStatus has labels', function () {
        expect(AgentStatus::Online->label())->toContain('Online');
        expect(AgentStatus::Offline->label())->toContain('Offline');
        expect(AgentStatus::Busy->label())->toContain('In Call');
    });

});

// ══════════════════════════════════════════════════════════════════
// DTO UNIT TESTS
// ══════════════════════════════════════════════════════════════════

describe('DTOs', function () {

    it('CallDTO converts to array', function () {
        $dto = new CallDTO(
            channel:   'PJSIP/101-test',
            caller:    '01711XXXXXX',
            extension: '101',
            direction: CallDirection::Inbound,
            status:    CallStatus::Answered,
            duration:  120,
        );

        $arr = $dto->toArray();
        expect($arr['caller'])->toBe('01711XXXXXX');
        expect($arr['extension'])->toBe('101');
        expect($arr['duration'])->toBe(120);
    });

    it('CallDTO detects missed call', function () {
        $dto = new CallDTO(
            channel:    'PJSIP/101-test',
            caller:     '01711XXXXXX',
            extension:  '101',
            direction:  CallDirection::Inbound,
            status:     CallStatus::Ended,
            answeredAt: null,
        );
        expect($dto->isMissed())->toBeTrue();
    });

    it('OriginateDTO can chain modifiers', function () {
        $dto = OriginateDTO::make('101', '01711XXXXXX')
            ->withCallerId('My Company')
            ->withTimeout(60000)
            ->withVariable('CRM_ID', '12345');

        expect($dto->from)->toBe('101');
        expect($dto->to)->toBe('01711XXXXXX');
        expect($dto->callerId)->toBe('My Company');
        expect($dto->timeout)->toBe(60000);
        expect($dto->variables['CRM_ID'])->toBe('12345');
    });

    it('CampaignDTO creates broadcast campaign', function () {
        $dto = CampaignDTO::broadcast('Promo', ['01711XXXXXX'], 'audio.wav');
        expect($dto->name)->toBe('Promo');
        expect($dto->type)->toBe('broadcast');
    });

    it('CampaignDTO creates ivr campaign', function () {
        $dto = CampaignDTO::withIVR('Survey', ['01711XXXXXX'], 'audio.wav', ['1' => ['action' => 'transfer', 'value' => '101']]);
        expect($dto->type)->toBe('ivr_survey');
    });

    it('AgentDTO detects available agent', function () {
        $dto = \BitDreamIT\MikoPBX\DTOs\AgentDTO::fromArray([
            'number' => '101', 'status' => 'REGISTERED', 'name' => 'Rahim',
        ]);
        expect($dto->isAvailable())->toBeTrue();
        expect($dto->isInCall())->toBeFalse();
    });

});

// ══════════════════════════════════════════════════════════════════
// TRAITS UNIT TESTS
// ══════════════════════════════════════════════════════════════════

describe('Traits', function () {

    it('FormatsCallDuration formats seconds correctly', function () {
        $obj = new class { use FormatsCallDuration; };

        expect($obj->formatDuration(45))->toBe('45s');
        expect($obj->formatDuration(90))->toBe('1m 30s');
        expect($obj->formatDuration(3700))->toBe('1h 1m 40s');
    });

    it('FormatsCallDuration returns human duration', function () {
        $obj = new class { use FormatsCallDuration; };

        expect($obj->formatDurationHuman(30))->toBe('less than a minute');
        expect($obj->formatDurationHuman(60))->toBe('1 minute');
        expect($obj->formatDurationHuman(120))->toBe('2 minutes');
    });

    it('ValidatesPhoneNumber normalizes BD number', function () {
        $obj = new class { use ValidatesPhoneNumber; };

        expect($obj->normalizePhone('01711234567'))->toBe('+8801711234567');
        expect($obj->normalizePhone('+8801711234567'))->toBe('+8801711234567');
        expect($obj->normalizePhone('8801711234567'))->toBe('+8801711234567');
    });

    it('ValidatesPhoneNumber validates BD number', function () {
        $obj = new class { use ValidatesPhoneNumber; };

        expect($obj->isValidBDPhone('01711234567'))->toBeTrue();
        expect($obj->isValidBDPhone('01234567890'))->toBeFalse(); // invalid prefix
        expect($obj->isValidBDPhone('1234'))->toBeFalse();
    });

    it('ValidatesPhoneNumber formats for display', function () {
        $obj = new class { use ValidatesPhoneNumber; };

        expect($obj->formatPhoneDisplay('01711234567'))->toBe('017-1123-4567');
    });

});
