<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

class Collection extends AbstractCollection
{
    /**
     * Collection constructor.
     * @param array $items
     * @param callable|string|int|null $indexBy
     */
    public function __construct(array $items = [], $indexBy = null)
    {
        $items = (function (array ...$array) {
            return $array;
        })(...$items);

        parent::__construct($items, $indexBy);
    }
}
