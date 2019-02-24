<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Entity\Collection;

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

    public function testGetCollections()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertSame($this->collections, $collections->getCollections());
    }

    public function testGetCollectionIterator()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertInstanceOf(\ArrayIterator::class, $collections->getCollectionIterator());
        $this->assertSame(\count($this->collections), $collections->getCollectionIterator()->count());
    }

    public function testGetCollectionCount()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertSame(\count($this->collections), $collections->getCollectionCount());
    }

    public function testAll()
    {
        $collections = new CollectionCollection($this->collections);

        $this->assertSame([
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
        ], $collections->all());
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

        $this->assertInstanceOf(\MultipleIterator::class, $collections->getIterator());
        $this->assertSame(\count($this->collections), $collections->getIterator()->countIterators());
    }

    public function testCount()
    {
        $collections = new CollectionCollection($this->collections);
        $this->assertSame(4, $collections->count());
    }
}
