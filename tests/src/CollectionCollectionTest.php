<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Collection;

use Ixocreate\Collection\Collection;
use Ixocreate\Collection\CollectionCollection;
use PHPUnit\Framework\TestCase;

class CollectionCollectionTest extends TestCase
{
    private $collections = [];

    public function setUp()
    {
        $this->collections = [];

        $this->collections[] = new Collection([
            [
                'id' => 1,
                'name' => 'Eddard Stark',
                'age' => 34,
            ],
            [
                'id' => 2,
                'name' => 'Catelyn Stark',
                'age' => 33,
            ],
        ]);
        $this->collections[] = new Collection([
            [
                'id' => 3,
                'name' => 'Daenerys Targaryen',
                'age' => 13,
            ],
            [
                'id' => 4,
                'name' => 'Tyrion Lannister',
                'age' => 24,
            ],
        ]);
    }

    public function testDataType()
    {
        $this->expectException(\Throwable::class);
        new CollectionCollection(['id' => 1]);
    }

    public function testToArray()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertSame($this->collections, $collections->toArray());
    }

    public function testIterator()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertInstanceOf(\Iterator::class, $collections);
    }

    public function testCount()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertSame(\count($this->collections), $collections->count());
    }

    public function testKeys()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertSame([0, 1], $collections->keys()->toArray());
    }

    public function testIsEmpty()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertFalse($collections->isEmpty());

        $collections = new CollectionCollection([]);
        $this->assertTrue($collections->isEmpty());
    }

    public function testCombinedCount()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertSame(4, $collections->flatten(1)->values()->count());
    }
}
