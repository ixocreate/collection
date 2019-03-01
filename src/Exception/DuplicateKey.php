<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection\Exception;

class DuplicateKey extends \LogicException
{
    protected $message = 'Either call values() or strictUniqueKeys(false) before you act on the collection.';
}
