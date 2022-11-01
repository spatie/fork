<?php

namespace Spatie\Fork\Tests;

use Carbon\Carbon;
use DateTime;
use Spatie\Fork\Fork;

it('will execute the given closures', function () {
    $results = Fork::new()
        ->run(
            fn () => 1 + 1,
            fn () => 2 + 2,
        );

    expect($results)->toEqual([2, 4]);
});

it('will execute the given closure with concurrency cap ', function () {
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

it('can execute the closures concurrently', function () {
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
    $results = Fork::new()
        ->before(function () {
            global $globalBeforeValue;

            $globalBeforeValue = 2;
        })
        ->run(function () {
            global $globalBeforeValue;

            return 1 + $globalBeforeValue;
        });

    expect($results)->toEqual([3]);
});

test('the callable given to after runs after each callable', function () {
    $results = Fork::new()
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
        );

    expect($results)->toEqual([1]);
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

it('will not hang by truncating the result when large output is returned', function () {
    $result = Fork::new()
        ->run(
            fn () => file_get_contents('https://stitcher.io/rss'),
            fn () => file_get_contents('https://sebastiandedeyne.com/index.xml'),
            fn () => file_get_contents('https://rubenvanassche.com/rss/'),
        );

    expect($result)->toHaveCount(3);
});

it('can return objects', function () {
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
