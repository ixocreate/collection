<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Test\Collection;

use Ixocreate\Collection\Collection;
use Ixocreate\Collection\Exception\DuplicateKey;
use Ixocreate\Collection\Exception\InvalidArgument;
use Ixocreate\Collection\Exception\InvalidReturnValue;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private function data()
    {
        return require __DIR__ . '/../misc/data.php';
    }

    public function testCollectionAsInput()
    {
        $collection = new Collection($this->data());
        $collection2 = new Collection($collection);

        /**
         * Using Collection as input for another Collection should not result in DuplicateKey exceptions.
         * This makes sure it's properly wrapped internally.
         */
        $this->assertSame($collection->toArray(), $collection2->toArray());
    }

    public function testDeprecatedMethods()
    {
        $collection = new Collection($this->data());

        $this->assertSame($collection->extract('name')->toArray(), $collection->parts('name')->toArray());
        $this->assertSame($collection->takeNth(2)->toArray(), $collection->nth(2)->toArray());
        $this->assertSame($collection->toArray(), $collection->all());
        $this->assertSame($collection->unshift('name')->values()->toArray(), $collection->prepend('name')->values()->toArray());
    }

    public function testDuplicateKeyCheckRunsOnForeachWithKey()
    {
        $collection = (new Collection([['id' => 1], ['id' => 1]]))
            ->indexBy('id');

        $this->expectException(DuplicateKey::class);
        foreach ($collection as $key => $item) {
            //
        }
    }

    public function testDuplicateKeyCheckDoesNotRunOnForeachWithoutKey()
    {
        $collection = (new Collection([['id' => 1], ['id' => 1]]))
            ->indexBy('id');

        foreach ($collection as $item) {
            //
        }

        $this->addToAssertionCount(1);
    }

    public function testDuplicateKeyCheckGetsReset()
    {
        $expected = [['id' => 1, 'name' => 'One'], ['id' => 1, 'name' => 'Two']];

        $collection = (new Collection($expected))
            ->indexBy('id');

        /**
         * Each realizing call on the same collection would result in a DuplicateKeys exception after the first call
         * if keysUsed[] was not reset internally on rewind().
         */
        $this->assertSame($expected, $collection->values()->toArray());
        $this->assertSame($expected, $collection->values()->toArray());
        $this->assertSame(2, $collection->values()->count());
        $this->assertSame(2, $collection->values()->count());
    }

    public function testGeneratorCanBeRunMultipleTimes()
    {
        /**
         * Call filter or any other method that adds a generator to the pipeline.
         */
        $collection = (new Collection($this->data()))
            ->filter(function ($item) {
                return $item['age'] < 8;
            })
            ->indexBy('id');

        $expected = [
            6 => [
                'id' => 6,
                'name' => 'Brandon Stark',
                'age' => 7,
            ],
            15 => [
                'id' => 15,
                'name' => 'Brandon Stark Twin',
                'age' => 7,
            ],
        ];

        /**
         * Each call on the same collection would result in an "Exception : Cannot rewind a generator that was already run"
         * if a generator with valid() === false was not recreated on each rewind()
         */
        $this->assertSame(2, $collection->count());
        $this->assertSame($expected, $collection->toArray());
        $this->assertSame($expected, $collection->toArray());
        $this->assertSame(2, $collection->count());
        $this->assertSame($expected, $collection->toArray());
    }

    public function testInvalidInput()
    {
        $this->expectException(InvalidArgument::class);
        new Collection(false);
    }

    public function testNonScalarKeyIsCastToString()
    {
        $entity = new class() {
            public function __toString()
            {
                return 'foo';
            }
        };

        $data = [
            'bar' => $entity,
        ];

        $collection = (new Collection($data))->flip();
        $this->assertSame(['foo' => 'bar'], $collection->toArray());
    }

    public function testSelectorWithInvalidObjectProperty()
    {
        $this->expectException(InvalidReturnValue::class);
        (new Collection([(object)['foo' => 'bar']], 'nope'))->keys()->toArray();
    }

    public function testIterator()
    {
        $collection = new Collection($this->data());
        $this->assertInstanceOf(\Iterator::class, $collection);
    }

    public function testJsonEncode()
    {
        $collection = new Collection($this->data());
        $this->assertSame(\json_encode($this->data()), \json_encode($collection));
    }

    public function testToArray()
    {
        $collection = new Collection($this->data());
        $this->assertSame($this->data(), $collection->toArray());
        $this->assertSame(\array_values($collection->toArray()), $collection->values()->toArray());
    }
}
