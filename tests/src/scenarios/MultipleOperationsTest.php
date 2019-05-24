<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Test\Collection\Scenarios;

use Ixocreate\Collection\Collection;
use PHPUnit\Framework\TestCase;

class MultipleOperationsTest extends TestCase
{
    /**
     * Example of a longer pipeline. If this was real code, you should probably split it into smaller chunks.
     * From Knapsack's test scenarios.
     */
    public function testIt()
    {
        $array = [1, 2, 8, 3, 7, 5, 1, 4, 4,];
        $collection = new Collection($array);
        $result = $collection
            ->reject(function ($v) {
                return $v > 2;
            })
            ->filter(function ($k) {
                return $k > 5;
            })
            ->distinct()
            ->concat([1, 2])
            ->map(function ($i) {
                return [$i, $i + 1];
            })
            ->flatten()
            ->values()
            ->sort(function ($a, $b) {
                return $a > $b;
            })
            ->slice(2, 5)
            ->groupBy(function ($v) {
                return $v % 2 == 0 ? 'even' : 'odd';
            })
            ->get('even')
            ->toArray();
        $this->assertEquals([2], $result);
    }
}
