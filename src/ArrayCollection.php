<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Traversable;

final class ArrayCollection extends AbstractCollection
{
    /**
     * @param callable|array|Traversable $items
     * @param callable|string|int|null $indexBy
     */
    public function __construct($items = [], $indexBy = null)
    {
        return parent::__construct(
            new Collection(
                (function (array ...$item) {
                    return $item;
                })(...$items),
                $indexBy
            )
        );
    }
}
