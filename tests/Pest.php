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

function runTransferScenario(string $scenario, bool $serialized = false, bool $withoutPosix = false, bool $catchExceptions = false): array
{
    $process = proc_open(
        [PHP_BINARY, __DIR__.'/fixtures/incompleteTransfer.php', $scenario, (int) $serialized, (int) $withoutPosix, (int) $catchExceptions],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, $errors)
        ->and($errors)->toBe('');

    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}
