<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Test\Collection\Scenarios;

use Ixocreate\Collection\AbstractCollection;
use Ixocreate\Collection\Collection;
use PHPUnit\Framework\TestCase;
use Traversable;

class PerformanceTest extends TestCase
{
    public function testNestedCollectionCallCount()
    {
        $entityCollectionClass = new class() extends AbstractCollection {
            public $testTypeCount = 0;

            /**
             * @param callable|array|Traversable $items
             * @param callable|string|int|null $indexBy
             */
            public function __construct($items = [], $indexBy = null)
            {
                $items = new Collection($items);

                /**
                 * add type check
                 */
                $items = $items->each(function ($value) {
                    $this->testTypeCount++;
                });

                $items = $items->each(function ($value) {
                    // do something else
                });

                $items = $items->each(function ($value) {
                    // do something else
                });

                /**
                 * index by after type check
                 */
                if ($indexBy !== null) {
                    $items = $items->indexBy($indexBy);
                }

                parent::__construct($items);
            }
        };

        $data = \range(1, 10000);
        $entityCollection = new $entityCollectionClass($data);

        foreach ($entityCollection as $item) {
            // nothing
        }

        // foreach ($entityCollection as $item) {
        //     // nothing
        // }

        // $entityCollection
        //     ->each(function ($item) use (&$count) {
        //         // do something else
        //     })
        //     ->toArray();

        $this->assertSame(10000, $entityCollection->testTypeCount);
    }
}
