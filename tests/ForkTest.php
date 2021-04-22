<?php

namespace Spatie\Fork\Tests;

use PHPUnit\Framework\TestCase;
use Spatie\Fork\Fork;

class ForkTest extends TestCase
{
    protected float $startTime;

    public function setUp(): void
    {
        parent::setUp();

        $this->startTime = microtime(true);
    }

    /** @test */
    public function it_will_execute_the_given_closures()
    {
        $results = Fork::new()
            ->run(
                fn () => 1 + 1,
                fn () => 2 + 2,
            );

        $this->assertEquals([2, 4], $results);
    }

    /** @test */
    public function it_can_execute_the_closures_concurrently()
    {
        $results = Fork::new()
            ->run(
                function () {
                    sleep(1);

                    return 1 + 1;
                },
                function () {
                    sleep(2);

                    return 2 + 2;
                },
                function () {
                    sleep(1);

                    return 3 + 3;
                },
                function () {
                    sleep(1);

                    return 4 + 4;
                },
            );

        $this->assertEquals([2, 4, 6, 8], $results);

        $this->assertTookLessThanSeconds(3);
    }

    /** @test */
    public function the_callable_given_to_before_runs_before_each_callable()
    {
        $results = Fork::new()
            ->before(function () {
                global $globalBeforeValue;

                $globalBeforeValue = 2;
            })
            ->run(function () {
                global $globalBeforeValue;

                return 1 + $globalBeforeValue;
            });

        $this->assertEquals([3], $results);
    }

    /** @test */
    public function the_callable_given_to_after_runs_after_each_callable()
    {
        $results = Fork::new()
            ->after(function () {
                global $globalAfterValue;

                $this->assertEquals(3, $globalAfterValue + 2);
            })
            ->run(
                function () {
                    global $globalAfterValue;

                    $globalAfterValue = 1;

                    return $globalAfterValue;
                },
            );

        $this->assertEquals([1], $results);
    }

    protected function assertTookLessThanSeconds(int $expectedLessThanSeconds)
    {
        $currentTime = microtime(true);

        $usedTimeInSeconds = $currentTime - $this->startTime;

        $this->assertLessThan($expectedLessThanSeconds, $usedTimeInSeconds, "Took more than expected {$expectedLessThanSeconds} seconds");
    }
}
