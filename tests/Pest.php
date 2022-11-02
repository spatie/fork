<?php

use function PHPUnit\Framework\assertLessThan;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(\PHPUnit\Framework\TestCase::class)
    ->beforeEach(function () {
        $this->startTime = microtime(true);
    })
    ->in('.');

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
