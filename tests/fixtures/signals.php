<?php

use Spatie\Fork\Fork;

require __DIR__.'/../../vendor/autoload.php';

if (posix_setsid() === -1) {
    exit(2);
}

echo getmypid()."\n";
flush();

[$script, $scenario, $disposition, $signal] = $argv;
$signal = (int) $signal;
$received = [];
$handler = match ($disposition) {
    'default' => SIG_DFL,
    'ignored' => SIG_IGN,
    'custom' => function (int $signal) use (&$received): void {
        $received[] = $signal;
    },
};

foreach ([SIGQUIT, SIGTERM, SIGINT] as $handledSignal) {
    pcntl_signal($handledSignal, $handler);
}

$original = new RuntimeException('original');
$fork = Fork::new()->concurrent(1);

if ($scenario === 'before-failure') {
    $fork->before(parent: function () use ($original): void {
        throw $original;
    });
}

if ($scenario === 'after-failure') {
    $fork->after(parent: function () use ($original): void {
        throw $original;
    });
}

if ($scenario === 'startup-signal') {
    $fork->before(parent: fn () => posix_kill(getmypid(), $signal));
}

$exceptionType = null;
$results = null;

try {
    $results = $fork->run(
        function () use ($scenario, $signal): int {
            echo "started\n";
            flush();

            if ($scenario === 'active-signal') {
                posix_kill(posix_getppid(), $signal);
                sleep(30);
            }

            if ($scenario === 'child-failure') {
                exit(7);
            }

            return 11;
        },
        function (): int {
            echo "queued\n";

            return 29;
        },
    );
} catch (Throwable $exception) {
    $exceptionType = $exception === $original ? 'original' : $exception::class;
}

$restored = [];

foreach ([SIGQUIT, SIGTERM, SIGINT] as $handledSignal) {
    $restored[] = pcntl_signal_get_handler($handledSignal) === $handler;
}

if ($disposition !== 'default') {
    posix_kill(getmypid(), $signal);
    pcntl_signal_dispatch();
}

echo json_encode([
    'restored' => $restored,
    'received' => $received,
    'exception' => $exceptionType,
    'results' => $results,
], JSON_THROW_ON_ERROR)."\n";
