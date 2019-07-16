<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Ixocreate\Collection\Exception\InvalidType;

final class CollectionCollection extends AbstractCollection
{
    public function __construct($items = [])
    {
        $items = new Collection($items);

        /**
         * add type check
         */
        $items = $items->each(function ($value) {
            if (!($value instanceof CollectionInterface)) {
                throw new InvalidType('All items have to be of type ' . CollectionInterface::class . '. Got item of type ' . \gettype($value));
            }
        });

        parent::__construct($items);
    }
}
