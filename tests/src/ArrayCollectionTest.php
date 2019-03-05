<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Collection;

use Ixocreate\Collection\ArrayCollection;
use Ixocreate\Collection\Exception\InvalidType;
use PHPUnit\Framework\TestCase;

class ArrayCollectionTest extends TestCase
{
    private function data()
    {
        return require '../misc/data.php';
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

    public function testInvalidTypeException()
    {
        $this->expectException(InvalidType::class);
        (new ArrayCollection([new \stdClass()]))->toArray();
    }
}
