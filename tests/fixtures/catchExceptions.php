<?php

use Spatie\Fork\Exceptions\TaskFailed;
use Spatie\Fork\Fork;

require __DIR__.'/../../vendor/autoload.php';

class CatchTaskMetadata
{
    public function __construct(
        public string $events,
        public int $parentPid,
        public string $scenario,
    ) {}

    public function __serialize(): array
    {
        file_put_contents($this->events, 'serialize:'.(int) (getmypid() === $this->parentPid)."\n", FILE_APPEND);

        if ($this->scenario === 'serialize-failure') {
            throw new LogicException('Cannot serialize metadata');
        }

        return get_object_vars($this);
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }

        file_put_contents($this->events, 'unserialize:'.(int) (getmypid() === $this->parentPid)."\n", FILE_APPEND);

        if ($this->scenario === 'restore-failure' && getmypid() === $this->parentPid) {
            throw new LogicException('Cannot restore metadata in parent');
        }
    }
}

class CatchTaskException extends RuntimeException
{
    public CatchTaskMetadata $metadata;
}

[$script, $scenario] = $argv;
$events = tempnam(sys_get_temp_dir(), 'fork-catch-');
$parentPid = getmypid();
$original = new CatchTaskException('original failure', 73, new LogicException('previous failure', 19));
$original->metadata = new CatchTaskMetadata($events, $parentPid, $scenario);
$large = new RuntimeException(str_repeat('0123456789abcdef', 65_536), 51);
$fallback = fn () => 'a closure need not be serialized in the parent';
$caught = null;
$orders = [];
$outputs = [];
$callbackPids = [];
$callbackException = new LogicException('catch callback failed');
$restored = [];
$handlers = [];
[$parentGate, $childGate] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

foreach ([SIGINT, SIGTERM, SIGQUIT] as $signal) {
    $handlers[$signal] = pcntl_signal_get_handler($signal);
}

pcntl_async_signals(true);
pcntl_signal(SIGALRM, fn () => exit(2));
pcntl_alarm(15);
$thrown = null;
$results = null;

try {
    $results = Fork::new()->concurrent(2)
        ->catch(function (Throwable $exception, int $order) use (&$caught, &$orders, &$callbackPids, $fallback, $scenario, $callbackException) {
            $caught = $exception;
            $orders[] = $order;
            $callbackPids[] = getmypid();

            if ($scenario === 'callback-throw') {
                throw $callbackException;
            }

            return $fallback;
        })
        ->after(parent: function ($output) use (&$outputs, $parentGate): void {
            $outputs[] = is_callable($output) ? $output() : $output;

            if ($output === 'queued sibling') {
                fwrite($parentGate, 'go');
            }
        })
        ->run(
            function () use ($events, $childGate): string {
                fread($childGate, 1);
                file_put_contents($events.'.sibling', 'finished');

                return 'slow sibling';
            },
            function () use ($original, $large, $scenario): never {
                if ($scenario === 'large') {
                    throw $large;
                }

                if ($scenario === 'exit') {
                    exit(7);
                }

                throw $original;
            },
            function () use ($events): string {
                file_put_contents($events.'.queued', 'finished');

                return 'queued sibling';
            },
        );
} catch (Throwable $exception) {
    $thrown = $exception === $callbackException ? 'original callback exception' : $exception::class;
    fwrite($parentGate, 'go');
}

foreach ($handlers as $signal => $handler) {
    $restored[] = pcntl_signal_get_handler($signal) === $handler;
}

while (pcntl_waitpid(-1, $status) > 0) {
}

$report = [
    'posix' => extension_loaded('posix'),
    'thrown' => $thrown,
    'orders' => $orders,
    'callbackInParent' => $callbackPids === [getmypid()],
    'fallbackIdentity' => ($results[1] ?? null) === $fallback,
    'results' => $results === null ? null : array_map(fn ($output) => is_callable($output) ? $output() : $output, $results),
    'outputs' => $outputs,
    'class' => $caught === null ? null : $caught::class,
    'code' => $caught?->getCode(),
    'file' => $caught?->getFile(),
    'line' => $caught?->getLine(),
    'originalLine' => $original->getLine(),
    'message' => $caught?->getMessage(),
    'previous' => $caught?->getPrevious()?->getMessage(),
    'metadataMatches' => $caught instanceof CatchTaskException && $caught->metadata->parentPid === $parentPid,
    'originalClass' => $caught instanceof TaskFailed ? $caught->originalClass : null,
    'originalTrace' => $caught instanceof TaskFailed ? $caught->originalTrace === $original->getTraceAsString() : null,
    'events' => file($events, FILE_IGNORE_NEW_LINES),
    'siblingFinished' => file_exists($events.'.sibling'),
    'queuedFinished' => file_exists($events.'.queued'),
    'restored' => $restored,
];

foreach (glob($events.'*') as $file) {
    unlink($file);
}

pcntl_alarm(0);
echo json_encode($report, JSON_THROW_ON_ERROR);
