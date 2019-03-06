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
use Ixocreate\Collection\Exception\InvalidType;
use PHPUnit\Framework\TestCase;

class CollectionCollectionTest extends TestCase
{
    private $data;

    public function setUp()
    {
        $this->data = [
            new Collection([
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
            ]),
            new Collection([
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
            ]),
        ];
    }

    public function testCollection()
    {
        $collection = new CollectionCollection($this->data);
        $this->assertCount(2, $collection);

        $collection = new CollectionCollection($this->data);
        $this->assertSame($this->data, $collection->toArray());
    }

    public function testInvalidTypeException()
    {
        $this->expectException(InvalidType::class);
        (new CollectionCollection([['id' => 1]]))->toArray();
    }
}
