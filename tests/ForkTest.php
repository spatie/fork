<?php

use Carbon\Carbon;
use Spatie\Fork\Exceptions\CouldNotManageTask;
use Spatie\Fork\Exceptions\TaskFailed;
use Spatie\Fork\Fork;

test('will execute the given closures')
    ->expect(
        fn () => Fork::new()->run(
            fn () => 1 + 1,
            fn () => 2 + 2,
        )
    )->toEqual([2, 4])->not()->toThrow(CouldNotManageTask::class);

test('will execute the given closure with concurrency cap', function () {
    $results = Fork::new()
        ->concurrent(2)
        ->run(
            function () {
                sleep(1);

                return Carbon::now()->second;
            },
            function () {
                sleep(1);

                return Carbon::now()->second;
            },
            function () {
                sleep(1);

                return Carbon::now()->second;
            },
        );

    expect($results[1])->toEqual($results[0])
        ->and($results[2])->not->toEqual($results[1]);
});

test('can execute the closures concurrently', function () {
    Fork::new()
        ->run(
            ...array_fill(
                start_index: 0,
                count: 20,
                value: fn () => usleep(100_000),
            ) // 1/10th of a second each
        );

    assertTookLessThanSeconds(1);
});

test('the callable given to before runs before each callable', function () {
    expect(
        Fork::new()
            ->before(function () {
                global $globalBeforeValue;

                $globalBeforeValue = 2;
            })
            ->run(function () {
                global $globalBeforeValue;

                return 1 + $globalBeforeValue;
            })
    )->toEqual([3]);
});

test('the callable given to after runs after each callable', function () {
    expect(
        Fork::new()
            ->after(function () {
                global $globalAfterValue;

                expect($globalAfterValue + 2)->toEqual(3);
            })
            ->run(
                function () {
                    global $globalAfterValue;

                    $globalAfterValue = 1;

                    return $globalAfterValue;
                },
            )
    )->toEqual([1]);
});

test('the callable given to before can be run in the parent process', function () {
    $value = 0;

    Fork::new()
        ->before(parent: function () use (&$value) {
            $value++;
        })
        ->run(fn () => 1, fn () => 2);

    expect($value)->toEqual(2);
});

test('the callable given to after can be run in the parent process', function () {
    $value = 0;

    Fork::new()
        ->after(parent: function () use (&$value) {
            $value++;
        })
        ->run(fn () => 1, fn () => 2);

    expect($value)->toEqual(2);
});

test('events of child processes are isolated from each other', function () {
    $value = 2;
    Fork::new()
        ->before(function () use (&$value) {
            $value++;
            global $x;
            $x = 1;
        })
        ->after(function () use (&$value) {
            $value++;
            global $x;
            expect($x)->toEqual(2);
        })
        ->run(function () {
            global $x;
            $x++;
        }, function () {
            global $x;
            $x++;
        });

    expect($value)->toEqual(2);
});

test('will not hang by truncating the result when large output is returned', function () {
    $value = str_repeat('#', 1048576); // 1MB
    expect(
        // compare md5 instead of content to avoid large text dumps in case the test fails
        array_map(
            'md5',
            Fork::new()->run(
                ...array_fill(0, 16, fn () => $value)
            )
        )
    )->toEqual(array_fill(0, 16, md5($value)));
});

test('can return objects', function () {
    $result = Fork::new()
        ->run(
            fn () => new DateTime('2021-01-01'),
            fn () => new DateTime('2021-01-02'),
        );

    expect($result[0]->format('Y-m-d'))->toEqual('2021-01-01')
        ->and($result[1]->format('Y-m-d'))->toEqual('2021-01-02');
});

test('output in after', function () {
    Fork::new()
        ->after(
            parent: function (int $i) {
                expect($i)->toEqual(1);
            },
        )
        ->run(
            fn () => 1
        );
});

test('allow 2nd process to be done before the 1st', function () {
    expect(
        Fork::new()->run(
            static function () {
                usleep(200_000);

                return 2;
            },
            static function () {
                usleep(100_000);

                return 1;
            },
        )
    )->toEqual([2, 1]);
});

test('it throws exception in case of unexpected exit status', function () {
    expect(
        fn () => Fork::new()->run(
            static function () {
                exit(1);
            },
        )
    )->toThrow(CouldNotManageTask::class);
});

test('a task that throws returns an empty string when there is no catch callback', function () {
    expect(
        Fork::new()->run(
            static fn () => throw new RuntimeException('Something went wrong'),
            static fn () => 'second task',
        )
    )->toEqual(['', 'second task']);
});

test('the catch callback receives the exception and the order of the task that threw', function () {
    $results = Fork::new()
        ->catch(fn (Throwable $exception, int $order) => get_class($exception).": {$exception->getMessage()} in task {$order}")
        ->run(
            static fn () => 'first task',
            static fn () => throw new RuntimeException('Something went wrong', 42),
        );

    expect($results)->toEqual(['first task', 'RuntimeException: Something went wrong in task 1']);
});

test('the catch callback receives the original exception', function () {
    $caught = null;

    Fork::new()
        ->catch(function (Throwable $exception) use (&$caught) {
            $caught = $exception;
        })
        ->run(static fn () => throw new RuntimeException('Something went wrong', 42));

    expect($caught)->toBeInstanceOf(RuntimeException::class)
        ->and($caught->getMessage())->toBe('Something went wrong')
        ->and($caught->getCode())->toBe(42)
        ->and($caught->getFile())->toBe(__FILE__);
});

test('the output of the catch callback is passed to the callable given to after in the parent process', function () {
    $outputs = [];

    Fork::new()
        ->catch(fn (Throwable $exception) => 'caught')
        ->after(parent: function ($output) use (&$outputs) {
            $outputs[] = $output;
        })
        ->run(static fn () => throw new RuntimeException('Something went wrong'));

    expect($outputs)->toBe(['caught']);
});

test('the catch callback receives a description of an exception that cannot be serialized', function () {
    $caught = null;

    Fork::new()
        ->catch(function (Throwable $exception) use (&$caught) {
            $caught = $exception;
        })
        ->run(static function () {
            throw new class('Holds a closure') extends RuntimeException
            {
                public Closure $callback;
            };
        });

    expect($caught)->toBeInstanceOf(TaskFailed::class)
        ->and($caught->getMessage())->toContain('Holds a closure')
        ->and($caught->originalClass)->toContain('RuntimeException@anonymous')
        ->and($caught->getFile())->toBe(__FILE__);
});

test('the catch callback receives the exception when the output of a task cannot be serialized', function () {
    $caught = null;

    $results = Fork::new()
        ->catch(function (Throwable $exception) use (&$caught) {
            $caught = $exception;

            return 'caught';
        })
        ->run(
            static fn () => static fn () => 'closures cannot be serialized',
            static fn () => 'second task',
        );

    expect($results)->toEqual(['caught', 'second task'])
        ->and($caught->getMessage())->toContain("Serialization of 'Closure' is not allowed");
});

test('incomplete transfers fail instead of returning truncated results', function (bool $serialized, bool $withoutPosix) {
    $report = runTransferScenario('timeout', $serialized, $withoutPosix);

    expect($report['exception'])->toBe(CouldNotManageTask::class)
        ->and($report['callbacks'])->toBe(['first task'])
        ->and($report['childSuccess'])->toBeFalse();
})->with([false, true])->with([false, true]);

test('large transfers succeed when the busy parent resumes within the wait limit', function (bool $serialized, bool $withoutPosix) {
    $report = runTransferScenario('success', $serialized, $withoutPosix);

    expect($report['exception'])->toBeNull()
        ->and($report['exact'])->toBeTrue()
        ->and($report['callbacks'])->toBe(['first task', 'large result'])
        ->and($report['childSuccess'])->toBeTrue();
})->with([false, true])->with([false, true]);

test('an abandoned fork writer fails even while a sibling retains its peer socket', function () {
    $report = runTransferScenario('abandonedFork');

    expect($report['parentException'])->toBe('Parent stopped reading')
        ->and($report['writerFailed'])->toBeTrue()
        ->and($report['holderRunning'])->toBeTrue()
        ->and($report['childSuccess'])->toBeFalse();
});

test('a task that is stopped and continued is not treated as failed', function () {
    if (posix_getpgrp() !== getmypid()) {
        expect(posix_setpgid(0, 0))->toBeTrue();
    }

    expect(posix_getpgrp())->toBe(getmypid());

    expect(
        Fork::new()->run(
            static function () {
                posix_kill(getmypid(), SIGSTOP);

                return 'continued';
            },
            static function () {
                usleep(300_000);

                posix_kill(0, SIGCONT);

                return 'other task';
            },
        )
    )->toEqual(['continued', 'other task']);
});

test('fork failures stop launching tasks and preserve exception and cleanup behavior', function (int $failAt, bool $warningException, bool $withoutPosix) {
    $process = proc_open(
        [PHP_BINARY, __DIR__.'/fixtures/forkFailure.php', $failAt, (int) $warningException, (int) $withoutPosix],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, $errors);

    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    $children = match ($failAt) {
        1 => [],
        2 => [1 => $withoutPosix ? 0 : -1],
        3 => [1 => -1, 2 => $withoutPosix ? 0 : -1],
    };

    expect($report['exception'])->toBe($warningException ? ErrorException::class : CouldNotManageTask::class)
        ->and($report['message'])->toContain($warningException ? 'fork warning converted to exception' : pcntl_strerror(PCNTL_EAGAIN))
        ->and($report['originalException'])->toBe($warningException)
        ->and($report['attempts'])->toBe($failAt)
        ->and($report['children'])->toBe($children)
        ->and($report['unrelatedChild'])->toBe(0)
        ->and($report['socketDifference'])->toBe(0)
        ->and($report['failFast'])->toBeTrue()
        ->and($report['reuse'])->toBe([73]);
})->with([1, 2, 3])->with([false, true])->with([false, true]);

test('subclasses can use their own fork method', function () {
    $fork = new class extends Fork
    {
        private function fork(string $value): string
        {
            return $value;
        }
    };

    expect($fork->run(fn () => 19))->toBe([19]);
});

test('run restores application handlers without changing signal handling during tasks', function (string $scenario, string $disposition, int $signal) {
    $process = proc_open(
        [PHP_BINARY, __DIR__.'/fixtures/signals.php', $scenario, $disposition, $signal],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $errors = '';
    $deadline = microtime(true) + 5;
    $group = null;

    try {
        do {
            $output .= stream_get_contents($pipes[1]);
            $errors .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);

            if (str_contains($output, "\n")) {
                $group = (int) strstr($output, "\n", true);
            }

            if (! $status['running']) {
                break;
            }

            usleep(1_000);
        } while (microtime(true) < $deadline);

        expect($status['running'])->toBeFalse('Signal scenario exceeded its external deadline');
        $output .= stream_get_contents($pipes[1]);
        $errors .= stream_get_contents($pipes[2]);

        expect($errors)->toBe('');
        expect($group)->toBe($status['pid']);

        $events = array_slice(explode("\n", trim($output)), 1);

        if (in_array($scenario, ['active-signal', 'startup-signal'])) {
            expect($status['signaled'])->toBeTrue()
                ->and($status['termsig'])->toBe(SIGKILL)
                ->and($events)->toBe($scenario === 'active-signal' ? ['started'] : []);

            return;
        }

        expect($status['exitcode'])->toBe(0);

        $report = json_decode(array_pop($events), true, flags: JSON_THROW_ON_ERROR);

        expect($report['restored'])->toBe([true, true, true])
            ->and($report['received'])->toBe($disposition === 'custom' ? [$signal] : [])
            ->and($report['exception'])->toBe(match ($scenario) {
                'before-failure', 'after-failure' => 'original',
                'child-failure' => CouldNotManageTask::class,
                default => null,
            })
            ->and($report['results'])->toBe($scenario === 'normal' ? [11, 29] : null)
            ->and(in_array('queued', $events))->toBe($scenario === 'normal');
    } finally {
        if ($group === proc_get_status($process)['pid']) {
            posix_kill(-$group, SIGKILL);
        }

        proc_terminate($process, SIGKILL);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
})->with(['normal', 'before-failure', 'after-failure', 'child-failure', 'active-signal', 'startup-signal'])
    ->with(['default', 'ignored', 'custom'])
    ->with([SIGTERM, SIGINT, SIGQUIT]);
