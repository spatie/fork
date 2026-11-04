<?php

namespace Spatie\Fork;

use ErrorException;
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

[$script, $failAt, $warningException, $withoutPosix] = $argv;
$failAt = (int) $failAt;
$withoutPosix = (bool) $withoutPosix;
$limits = posix_getrlimit();
$directory = sys_get_temp_dir().'/fork-failure-'.getmypid();
mkdir($directory);
posix_setsid();

$unrelatedPid = pcntl_fork();

if ($unrelatedPid === 0) {
    sleep(30);
    exit(0);
}

$cleanup = function () use ($limits, $directory, $unrelatedPid): void {
    posix_setrlimit(POSIX_RLIMIT_NPROC, $limits['soft maxproc'], $limits['hard maxproc']);

    foreach (glob($directory.'/*') as $file) {
        $pid = (int) file_get_contents($file);

        if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);
        }

        unlink($file);
    }

    posix_kill($unrelatedPid, SIGKILL);
    pcntl_waitpid($unrelatedPid, $status);
    rmdir($directory);
};

pcntl_async_signals(true);
pcntl_signal(SIGALRM, function () use ($cleanup): void {
    $cleanup();
    fwrite(STDERR, 'Timed out waiting for fork failure');
    exit(2);
});
pcntl_alarm(5);

$originalException = new ErrorException('fork warning converted to exception');
set_error_handler(function (int $severity, string $message) use ($warningException, $originalException): bool {
    if ($warningException) {
        throw $originalException;
    }

    return true;
});

$attempts = 0;
$fork = Fork::new()->concurrent(2)->before(parent: function () use (&$attempts, $failAt, $directory, $limits): void {
    $attempts++;

    if ($attempts !== $failAt) {
        return;
    }

    if ($failAt > 1) {
        while (! file_exists($directory.'/'.($failAt - 1))) {
            usleep(1_000);
        }
    }

    if (! posix_setrlimit(POSIX_RLIMIT_NPROC, 0, $limits['hard maxproc'])) {
        throw new RuntimeException('Could not limit child processes');
    }
});

$socketCount = count(glob('/proc/self/fd/*'));
$start = microtime(true);

try {
    $fork->run(
        function () use ($directory, $failAt): int {
            file_put_contents($directory.'/1', getmypid());

            if ($failAt === 3) {
                return 11;
            }

            sleep(30);

            return 11;
        },
        function () use ($directory): int {
            file_put_contents($directory.'/2', getmypid());
            sleep(30);

            return 22;
        },
        function () use ($directory): int {
            file_put_contents($directory.'/3', getmypid());

            return 33;
        },
        function () use ($directory): int {
            file_put_contents($directory.'/4', getmypid());

            return 44;
        },
    );

    throw new RuntimeException('Expected a fork failure');
} catch (Throwable $exception) {
    $elapsed = microtime(true) - $start;
    restore_error_handler();
    posix_setrlimit(POSIX_RLIMIT_NPROC, $limits['soft maxproc'], $limits['hard maxproc']);

    $children = [];

    foreach (glob($directory.'/*') as $file) {
        $children[basename($file)] = pcntl_waitpid((int) file_get_contents($file), $status, WNOHANG);
    }

    $report = [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
        'originalException' => $exception === $originalException,
        'attempts' => $attempts,
        'children' => $children,
        'unrelatedChild' => pcntl_waitpid($unrelatedPid, $status, WNOHANG),
        'socketDifference' => count(glob('/proc/self/fd/*')) - $socketCount,
        'failFast' => $elapsed < 2,
        'reuse' => $fork->before()->run(fn () => 73),
    ];

    $cleanup();
    pcntl_alarm(0);

    echo json_encode($report, JSON_THROW_ON_ERROR);
}
