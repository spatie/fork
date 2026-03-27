<?php

use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertLessThan;

uses(TestCase::class)
    ->beforeEach(function () {
        $this->startTime = microtime(true);
    })
    ->in('.');

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
