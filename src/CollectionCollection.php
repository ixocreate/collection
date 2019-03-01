<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Ixocreate\Contract\Collection\CollectionInterface;

final class CollectionCollection extends AbstractCollection
{
    public function __construct($items = [])
    {
        parent::__construct(
            (function (CollectionInterface ...$collection) {
                return $collection;
            })(...$items)
        );
    }
}
