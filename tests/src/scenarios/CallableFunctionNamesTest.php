<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Test\Collection\Scenarios;

use Ixocreate\Collection\Collection;
use PHPUnit\Framework\TestCase;

class CallableFunctionNamesTest extends TestCase
{
    /**
     * Example that it's possible to use callable function names as arguments.
     * From Knapsack's test scenarios.
     */
    public function testIt()
    {
        $result = (new Collection([2, 1]))
            ->concat([3, 4])
            ->sort(function ($a, $b) {
                if ($a == $b) {
                    return 0;
                }

                return $a < $b ? -1 : 1;
            })
            ->values()
            ->toArray();

        $expected = [1, 2, 3, 4];

        $this->assertEquals($expected, $result);
    }
}
