<?php

use Spatie\Fork\Connection;

test('it can create a connected pair of sockets', function () {
    [$socketToParent, $socketToChild] = Connection::createPair();

    $socketToChild->write('hello');

    $received = implode('', iterator_to_array($socketToParent->read(), false));

    $socketToParent->close();
    $socketToChild->close();

    expect($received)->toBe('hello');
});

test('writes to a closed peer fail explicitly', function () {
    $report = runTransferScenario('closedPeer');

    expect($report['exception'])->toBe(RuntimeException::class);
});

test('writes remain bounded when a sibling retains the closed peer socket', function () {
    $report = runTransferScenario('inheritedPeer');

    expect($report['exception'])->toBe(RuntimeException::class)
        ->and($report['elapsed'])->toBeGreaterThanOrEqual(4.5)
        ->and($report['elapsed'])->toBeLessThan(8)
        ->and($report['holderRunning'])->toBeTrue();
});

test('interrupted selects do not renew the no progress limit', function () {
    $report = runTransferScenario('interrupted');

    expect($report['exception'])->toBe(RuntimeException::class)
        ->and($report['interruptions'])->toBeGreaterThan(5)
        ->and($report['elapsed'])->toBeGreaterThanOrEqual(4.5)
        ->and($report['elapsed'])->toBeLessThan(8);
});

test('a progressing transfer may take longer than the no progress limit', function () {
    $report = runTransferScenario('progress');

    expect($report['exception'])->toBeNull()
        ->and($report['exact'])->toBeTrue()
        ->and($report['elapsed'])->toBeGreaterThan(5);
});
