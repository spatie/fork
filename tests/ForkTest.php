<?php

namespace Spatie\Fork\Tests;

use PHPUnit\Framework\TestCase;
use Spatie\Fork\Fork;

class ForkTest extends TestCase
{
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
        $startTime = microtime(true);

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

        $this->assertTookLessThanSeconds(3, $startTime);
    }

    protected function assertTookLessThanSeconds(int $expectedLessThanSeconds, float $startTime)
    {
        $currentTime = microtime(true);

        $usedTimeInSeconds = $currentTime - $startTime;

        $this->assertLessThan($expectedLessThanSeconds, $usedTimeInSeconds, "Took more than expected {$expectedLessThanSeconds} seconds");
    }
}
