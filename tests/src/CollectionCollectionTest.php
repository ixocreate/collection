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

    public function testDataIntegrityInvalidDataException()
    {
        $this->expectException(\Throwable::class);
        new Collection(['id' => 1]);
    }

    public function testAll()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertSame($this->collections, $collections->all());
    }

    public function testIterator()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertInstanceOf(\Iterator::class, $collections->iterator());
        $this->assertSame(\count($this->collections), $collections->iterator()->count());
    }

    public function testCount()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertSame(\count($this->collections), $collections->count());
    }

    public function testCombinedAll()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertEquals((new Collection([
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
        ]))->all(), $collections->combinedAll());
    }

    public function testKeys()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertSame([0, 1], $collections->keys());
    }

    public function testEach()
    {
        $collections = new CollectionCollection($this->collections);

        $i = 0;
        $result = [];
        $collections->each(function ($item) use (&$result, &$i) {
            if ($i > 0) {
                return false;
            }
            $result[] = $item;

            $i++;
        });

        $this->assertSame([$this->collections[0]], $result);
    }

    public function testIsEmpty()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertFalse($collections->isEmpty());

        $collections = new CollectionCollection([]);
        $this->assertTrue($collections->isEmpty());
    }

    public function testGetIterator()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertInstanceOf(\MultipleIterator::class, $collections->combinedIterator());
        $this->assertSame(\count($this->collections), $collections->combinedIterator()->countIterators());
    }

    public function testCombinedCount()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertSame(4, $collections->combinedCount());
    }
}
