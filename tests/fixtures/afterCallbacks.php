<?php

use Spatie\Fork\Fork;

require __DIR__.'/../../vendor/autoload.php';

[$script, $scenario, $process, $serialized] = $argv;
$serialized = (bool) $serialized;
$log = tempnam(sys_get_temp_dir(), 'fork-after-');
$record = fn (array $arguments) => file_put_contents($log, json_encode($arguments).PHP_EOL, FILE_APPEND | LOCK_EX);
$callback = match ($scenario) {
    'print_r' => print_r(...),
    'strlen' => strlen(...),
    'single' => function ($output) use ($record): void {
        $record([func_num_args(), $output]);
    },
    'optional' => function ($output, $label = 'default') use ($record): void {
        $record([$output, $label]);
    },
    'variadic' => fn (...$arguments) => $record($arguments),
    'object' => (new class($record)
    {
        public function __construct(public Closure $record) {}

        public function __invoke($output, $label = 'default'): void
        {
            ($this->record)([$output, $label]);
        }
    })(...),
    default => null,
};
$legacy = [];
$ordered = [];
$original = new RuntimeException('callback failure');
$handlers = array_map(pcntl_signal_get_handler(...), [SIGQUIT, SIGTERM, SIGINT]);
$fork = Fork::new()->concurrent(2);
[$parentGate, $childGate] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
stream_set_timeout($parentGate, 2);
stream_set_timeout($childGate, 2);
$started = 0;
$fork->before(parent: function () use (&$started, $childGate): void {
    if (++$started === 3) {
        fwrite($childGate, 'x');
    }
});

if ($callback) {
    $process === 'parent' ? $fork->after(parent: $callback) : $fork->after(child: $callback);
} else {
    $fork->after(
        child: fn ($output) => $record(['legacy', func_num_args(), $output]),
        parent: function ($output) use (&$legacy): void {
            $legacy[] = [func_num_args(), $output];
        },
    )->afterWithOrder(
        child: function ($output, int $order) use ($record, $scenario, $original): void {
            $record(['ordered', $order, $output]);

            if ($scenario === 'child-failure') {
                throw $original;
            }
        },
        parent: function ($output, int $order) use (&$ordered, $scenario, $original): void {
            $ordered[$order] = $output;

            if ($scenario === 'parent-failure') {
                throw $original;
            }
        },
    );

    if ($scenario === 'clear') {
        $fork->afterWithOrder();
    }

    if ($scenario === 'replace') {
        $fork->afterWithOrder(parent: new class($record)
        {
            public function __construct(public Closure $record) {}

            public function __invoke($output, int $order): void
            {
                ($this->record)(['replacement', $order, $output]);
            }
        });
    }
}

$results = null;
$exception = null;

try {
    $results = $fork->run(
        function () use ($parentGate, $serialized) {
            fread($parentGate, 1);

            return $serialized ? ['slow' => 73] : 'slow';
        },
        function () use ($serialized, $scenario) {
            if ($scenario === 'task-failure') {
                exit(7);
            }

            if ($scenario === 'serialization-failure') {
                return fn () => 1;
            }

            return $serialized ? ['fast' => 29] : 'fast';
        },
        fn () => $serialized ? ['queued' => 41] : 'queued',
    );
} catch (Throwable $caught) {
    $exception = $caught === $original ? 'original' : $caught::class;
} finally {
    fclose($parentGate);
    fclose($childGate);
}

$events = array_map(fn (string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($log, FILE_IGNORE_NEW_LINES));
unlink($log);

echo PHP_EOL.'REPORT '.json_encode([
    'results' => $results,
    'exception' => $exception,
    'legacy' => $legacy,
    'ordered' => $ordered,
    'events' => $events,
    'restored' => $handlers === array_map(pcntl_signal_get_handler(...), [SIGQUIT, SIGTERM, SIGINT]),
    'posix' => extension_loaded('posix'),
], JSON_THROW_ON_ERROR).PHP_EOL;
