<?php

use BitDreamIT\MikoPBX\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/*
 * Custom expectations for MikoPBX tests.
 */
expect()->extend('toBeValidSipChannel', function () {
    return $this->toMatch('/^(PJSIP|SIP)\/.+$/');
});

expect()->extend('toBePhoneNumber', function () {
    return $this->toMatch('/^\d{7,15}$/');
});

expect()->extend('toBeCampaignStatus', function (string $status) {
    return $this->toBe($status);
});
