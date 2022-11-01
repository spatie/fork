<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

use function PHPUnit\Framework\assertLessThan;

uses(\PHPUnit\Framework\TestCase::class)
    ->beforeEach(function () {
        $this->startTime = microtime(true);
    })
    ->in('.');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function assertTookLessThanSeconds(int $expectedLessThanSeconds): void
{
    $currentTime = microtime(true);

    $usedTimeInSeconds = $currentTime - test()->startTime;

    assertLessThan(
        $expectedLessThanSeconds,
        $usedTimeInSeconds,
        "Took more than expected {$expectedLessThanSeconds} seconds"
    );
}
