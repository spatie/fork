<?php

use Carbon\Carbon;
use Spatie\Fork\Fork;

it('will execute the given closures')
    ->expect(
        fn () => Fork::new()->run(
            fn () => 1 + 1,
            fn () => 2 + 2,
        )
    )->toEqual([2, 4]);

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
            ...array_fill(0, 20, fn () => usleep(100_000)) // 1/10th of a second each
        );

    assertTookLessThanSeconds(1);
});

test('the callable given to before runs before each callable')
    ->expect(
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

test('the callable given to after runs after each callable')
    ->expect(
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

test('the callable given to before can be run in the parent process', function () {
    $value = 0;

    Fork::new()
        ->before(null, function () use (&$value) {
            $value++;
        })
        ->run(fn () => 1, fn () => 2);

    expect($value)->toEqual(2);
});

test('the callable given to after can be run in the parent process', function () {
    $value = 0;

    Fork::new()
        ->after(null, function () use (&$value) {
            $value++;
        })
        ->run(fn () => 1, fn () => 2);

    expect($value)->toEqual(2);
});

it('will not hang by truncating the result when large output is returned')
    ->expect(
        Fork::new()->run(
            fn () => file_get_contents('https://stitcher.io/rss'),
            fn () => file_get_contents('https://sebastiandedeyne.com/index.xml'),
            fn () => file_get_contents('https://rubenvanassche.com/rss/'),
        )
    )->toHaveCount(3);

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
        ->after(null, function (int $i) {
            expect($i)->toEqual(1);
        })
        ->run(
            fn () => 1
        );
});
