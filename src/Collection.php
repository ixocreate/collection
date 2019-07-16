<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOLIT GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Traversable;

final class Collection extends AbstractCollection
{
    /**
     * @param callable|array|Traversable $items
     * @param callable|string|int|null $indexBy
     */
    public function __construct($items = [], $indexBy = null)
    {
        if ($indexBy !== null) {
            $items = (new Collection($items))->indexBy($indexBy);
        }

        return parent::__construct($items);
    }
}
