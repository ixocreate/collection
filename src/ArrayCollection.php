<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Ixocreate\Collection\Exception\InvalidType;
use Traversable;

final class ArrayCollection extends AbstractCollection
{
    /**
     * @param callable|array|Traversable $items
     * @param callable|string|int|null $indexBy
     */
    public function __construct($items = [], $indexBy = null)
    {
        $items = new Collection($items);

        /**
         * add type check
         */
        $items = $items->each(function ($value) {
            if (!\is_array($value)) {
                throw new InvalidType('All items must be of type array. Got item of type ' . \gettype($value));
            }
        });

        /**
         * index by name after type check
         */
        if ($indexBy !== null) {
            $items = $items->indexBy($indexBy);
        }

        return parent::__construct($items);
    }
}
