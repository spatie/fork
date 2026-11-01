<?php

use Carbon\Carbon;
use Spatie\Fork\Exceptions\CouldNotManageTask;
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

test('the signal handlers installed before run are restored afterwards', function () {
    $handler = fn () => null;
    pcntl_signal(SIGTERM, $handler);

    try {
        Fork::new()->run(fn () => 1);

        expect(pcntl_signal_get_handler(SIGTERM))->toBe($handler);
    } finally {
        pcntl_signal(SIGTERM, SIG_DFL);
    }
});

test('a signal received while running is handed on to the handler installed before run', function () {
    $receivedSignals = [];
    pcntl_signal(SIGTERM, function (int $signal) use (&$receivedSignals) {
        $receivedSignals[] = $signal;
    });

    try {
        $results = Fork::new()
            ->concurrent(2)
            ->run(
                static function () {
                    usleep(200_000);
                    posix_kill(posix_getppid(), SIGTERM);
                    sleep(10);
                },
                static fn () => sleep(10),
                static fn () => 'never started',
            );

        expect($receivedSignals)->toBe([SIGTERM])
            ->and($results)->not->toHaveKey(2);

        assertTookLessThanSeconds(5);
    } finally {
        pcntl_signal(SIGTERM, SIG_DFL);
    }
});

test('a signal that was ignored before run stays ignored', function () {
    pcntl_signal(SIGTERM, SIG_IGN);

    try {
        $results = Fork::new()->run(
            static function () {
                posix_kill(posix_getppid(), SIGTERM);

                return 'first task';
            },
            static function () {
                usleep(200_000);

                return 'second task';
            },
        );

        expect($results)->toEqual(['first task', 'second task'])
            ->and(pcntl_signal_get_handler(SIGTERM))->toBe(SIG_IGN);
    } finally {
        pcntl_signal(SIGTERM, SIG_DFL);
    }
});

test('a signal received while running still ends the process when no handler was installed before run', function () {
    $script = sprintf(<<<'PHP'
        require %s;

        Spatie\Fork\Fork::new()->run(
            static function () {
                usleep(200_000);
                posix_kill(posix_getppid(), SIGTERM);
                sleep(10);
            },
            static fn () => sleep(10),
        );

        echo 'run() returned';
        PHP, var_export(__DIR__.'/../vendor/autoload.php', true));

    // Child tasks still running would keep the output pipe open, so this would take 10 seconds
    exec(PHP_BINARY.' -r '.escapeshellarg($script).' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(128 + SIGTERM)
        ->and($output)->not->toContain('run() returned');

    assertTookLessThanSeconds(5);
});
