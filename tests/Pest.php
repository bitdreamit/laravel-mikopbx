<?php

uses(
    \Orchestra\Testbench\TestCase::class,
)->in('Feature', 'Unit');

uses()->beforeEach(function () {
    \BitDreamIT\MikoPBX\Testing\MikoPBXFake::reset();
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidPhoneNumber', function () {
    $cleaned = preg_replace('/[^0-9]/', '', $this->value);
    return expect(preg_match('/^(880)?01[3-9]\d{8}$/', $cleaned))->toBe(1);
});

expect()->extend('toBeBetween', function (mixed $min, mixed $max) {
    return expect($this->value)->toBeGreaterThanOrEqual($min)->toBeLessThanOrEqual($max);
});
