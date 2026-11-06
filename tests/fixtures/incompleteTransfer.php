<?php

namespace Spatie\Fork;

use RuntimeException;
use Throwable;

use function extension_loaded as nativeExtensionLoaded;

require __DIR__.'/../../vendor/autoload.php';

function extension_loaded(string $extension): bool
{
    return $extension === 'posix' && $GLOBALS['withoutPosix']
        ? false
        : nativeExtensionLoaded($extension);
}

[$script, $scenario, $serialized, $withoutPosix] = $argv;
$withoutPosix = (bool) $withoutPosix;
$catchExceptions = (bool) ($argv[4] ?? false);
$directory = sys_get_temp_dir().'/fork-transfer-'.getmypid();
mkdir($directory);
$pids = [];

$cleanup = function () use ($directory, &$pids): void {
    if (file_exists($directory.'/pids')) {
        $pids = array_merge($pids, array_map('intval', file($directory.'/pids')));
    }

    foreach (array_unique($pids) as $pid) {
        if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);
        }
    }

    foreach (glob($directory.'/*') as $file) {
        unlink($file);
    }

    rmdir($directory);
};

pcntl_async_signals(true);
pcntl_signal(SIGALRM, function () use ($cleanup): void {
    $cleanup();
    fwrite(STDERR, 'Timed out waiting for transfer scenario');
    exit(2);
});
pcntl_alarm(20);

$payload = str_repeat('abcdefgh01234567', 65_536);
[$parentGate, $childGate] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

if ($scenario === 'closedPeer') {
    [$reader, $writer] = Connection::createPair();
    $reader->close();
    $exception = null;

    try {
        $writer->write('hello');
    } catch (Throwable $caught) {
        $exception = $caught::class;
    }

    $writer->close();
    $cleanup();
    pcntl_alarm(0);
    echo json_encode(['exception' => $exception], JSON_THROW_ON_ERROR);
    exit(0);
}

if (in_array($scenario, ['inheritedPeer', 'interrupted', 'progress'])) {
    [$reader, $writer] = Connection::createPair();
    if ($scenario === 'inheritedPeer') {
        $holderPid = pcntl_fork();

        if ($holderPid === 0) {
            $writer->close();
            sleep(30);
            exit(0);
        }

        $pids[] = $holderPid;
    }

    if ($scenario === 'progress') {
        $payload = str_repeat($payload, 4);
    }

    $writerPid = pcntl_fork();

    if ($writerPid === 0) {
        $reader->close();
        $started = microtime(true);
        $exception = null;
        $interruptions = 0;

        pcntl_signal(SIGUSR1, function () use (&$interruptions): void {
            $interruptions++;
        }, false);
        fwrite($childGate, 'ready'.PHP_EOL);

        try {
            $writer->write($payload);
        } catch (Throwable $caught) {
            $exception = $caught::class;
        }

        fwrite($childGate, json_encode([
            'exception' => $exception,
            'elapsed' => microtime(true) - $started,
            'interruptions' => $interruptions,
        ], JSON_THROW_ON_ERROR).PHP_EOL);
        exit(0);
    }

    $pids[] = $writerPid;
    $writer->close();
    fclose($childGate);
    fgets($parentGate);

    if ($scenario === 'inheritedPeer') {
        $reader->close();
    }

    $received = '';

    do {
        if ($scenario === 'progress') {
            foreach ($reader->read() as $chunk) {
                $received .= $chunk;
                usleep(2_000);
            }
        }

        usleep(100_000);

        if ($scenario === 'interrupted') {
            posix_kill($writerPid, SIGUSR1);
        }
    } while (pcntl_waitpid($writerPid, $status, WNOHANG) === 0);

    $report = json_decode(fgets($parentGate), true, flags: JSON_THROW_ON_ERROR);
    $report['exact'] = $received === $payload;
    $report['holderRunning'] = isset($holderPid) && pcntl_waitpid($holderPid, $status, WNOHANG) === 0;

    $cleanup();
    pcntl_alarm(0);
    echo json_encode($report, JSON_THROW_ON_ERROR);
    exit(0);
}

if ($scenario === 'abandonedFork') {
    $fork = Fork::new()->before(function () use ($directory): void {
        file_put_contents($directory.'/pids', getmypid().PHP_EOL, FILE_APPEND | LOCK_EX);
    })->after(
        child: function ($output) use ($directory): void {
            if ($output !== 'stop') {
                file_put_contents($directory.'/success', 'yes');
            }
        },
        parent: function (): void {
            throw new RuntimeException('Parent stopped reading');
        },
    );

    try {
        $fork->run(
            function () use ($directory, $payload) {
                file_put_contents($directory.'/writer', getmypid());

                return $payload;
            },
            function () use ($directory) {
                file_put_contents($directory.'/holder', getmypid());
                sleep(30);

                return 'holder';
            },
            function () use ($directory) {
                while (! file_exists($directory.'/writer') || ! file_exists($directory.'/holder')) {
                    usleep(1_000);
                }

                return 'stop';
            },
        );
    } catch (Throwable $caught) {
        $parentException = $caught->getMessage();
    }

    foreach ([SIGQUIT, SIGTERM, SIGINT] as $signal) {
        pcntl_signal($signal, SIG_DFL);
    }

    unset($fork, $caught);
    $writerPid = (int) file_get_contents($directory.'/writer');
    pcntl_waitpid($writerPid, $status);
    $report = [
        'parentException' => $parentException ?? null,
        'writerFailed' => pcntl_wifexited($status) && pcntl_wexitstatus($status) === 1,
        'holderRunning' => pcntl_waitpid((int) file_get_contents($directory.'/holder'), $status, WNOHANG) === 0,
        'childSuccess' => file_exists($directory.'/success'),
    ];

    $cleanup();
    pcntl_alarm(0);
    echo json_encode($report, JSON_THROW_ON_ERROR);
    exit(0);
}

$value = $serialized ? ['payload' => $payload, 'tail' => [23, false, null]] : $payload;
$callbacks = [];
$exception = null;
$results = null;

try {
    $fork = Fork::new()
        ->before(function () use ($directory): void {
            file_put_contents($directory.'/pids', getmypid().PHP_EOL, FILE_APPEND | LOCK_EX);
        })
        ->after(
            child: function ($output) use ($directory, $payload): void {
                if (strlen($output) >= strlen($payload)) {
                    file_put_contents($directory.'/success', 'yes');
                }
            },
            parent: function ($output) use ($parentGate, $scenario, &$callbacks): void {
                $callbacks[] = $output === 'first task' ? 'first task' : 'large result';

                if ($output !== 'first task') {
                    return;
                }

                fwrite($parentGate, 'start');
                fread($parentGate, 1);
                sleep($scenario === 'timeout' ? 6 : 1);
            },
        );

    if ($catchExceptions) {
        $fork->catch(fn () => throw new RuntimeException('Transfer failures must not reach catch'));
    }

    $results = $fork->run(
        fn () => 'first task',
        function () use ($childGate, $value) {
            fread($childGate, 5);
            fwrite($childGate, 'ready');

            return $value;
        },
    );
} catch (Throwable $caught) {
    $exception = $caught::class;
}

$report = [
    'exception' => $exception,
    'exact' => $results === ['first task', $value],
    'callbacks' => $callbacks,
    'childSuccess' => file_exists($directory.'/success'),
];

$cleanup();
pcntl_alarm(0);
echo json_encode($report, JSON_THROW_ON_ERROR);
