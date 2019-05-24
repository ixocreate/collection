<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Test\Collection;

use Ixocreate\Collection\ArrayCollection;
use Ixocreate\Collection\Exception\InvalidType;
use PHPUnit\Framework\TestCase;

class ArrayCollectionTest extends TestCase
{
    private function data()
    {
        return require __DIR__ . '/../misc/data.php';
    }

    public function testCollection()
    {
        $data = $this->data();
        $collection = new ArrayCollection($data);
        $this->assertCount(16, $collection);

        $data = $collection->toArray();
        $collection = new ArrayCollection($collection);
        $this->assertSame($data, $collection->toArray());
    }

    public function testIndexBy()
    {
        $data = $this->data();

        $expected = [];
        foreach ($data as $datum) {
            $expected[$datum['name']] = $datum;
        }

        $this->assertSame($expected, (new ArrayCollection($data, 'name'))->toArray());
    }

    public function testInvalidTypeException()
    {
        $this->expectException(InvalidType::class);
        (new ArrayCollection([new \stdClass()]))->toArray();
    }
}
