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

function runTransferScenario(string $scenario, bool $serialized = false, bool $withoutPosix = false): array
{
    $process = proc_open(
        [PHP_BINARY, __DIR__.'/fixtures/incompleteTransfer.php', $scenario, (int) $serialized, (int) $withoutPosix],
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

function runAfterCallbackScenario(string $scenario, string $callbackProcess = 'parent', bool $serialized = false, bool $withoutPosix = false): array
{
    $command = ['setsid', PHP_BINARY];

    if ($withoutPosix) {
        array_push($command, '-n', '-d', 'extension=sockets');
    }

    array_push($command, __DIR__.'/fixtures/afterCallbacks.php', $scenario, $callbackProcess, (int) $serialized);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $pid = proc_get_status($process)['pid'];
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $errors = '';
    $deadline = microtime(true) + 5;

    try {
        do {
            $output .= stream_get_contents($pipes[1]);
            $errors .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);

            if (! $status['running']) {
                break;
            }

            usleep(1_000);
        } while (microtime(true) < $deadline);

        expect($status['running'])->toBeFalse('Callback scenario exceeded its external deadline');
        $output .= stream_get_contents($pipes[1]);
        $errors .= stream_get_contents($pipes[2]);

        expect($status['exitcode'])->toBe(0, $errors)
            ->and($errors)->toBe('');

        [$printed, $json] = explode(PHP_EOL.'REPORT ', $output, 2);
        $report = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        expect($report['restored'])->toBeTrue()
            ->and($report['posix'])->toBe(! $withoutPosix);

        return ['printed' => $printed, ...$report];
    } finally {
        posix_kill(-$pid, SIGKILL);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}
