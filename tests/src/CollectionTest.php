<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Entity\Collection;

use Ixocreate\Collection\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testDataIntegrityInvalidDataException()
    {
        $this->expectException(\Throwable::class);
        new Collection(['id' => 1]);
    }
}
