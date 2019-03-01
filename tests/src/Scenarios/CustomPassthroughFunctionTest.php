<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Collection\Scenarios;

use Ixocreate\Collection\Collection;
use PHPUnit\Framework\TestCase;

class CustomPassthroughFunctionTest extends TestCase
{
    /**
     * Example of implementing a transpose function and how to apply it over a collection.
     *
     * For more on how this can be useful: http://adamwathan.me/2016/04/06/cleaning-up-form-input-with-transpose/
     */
    public function testIt()
    {
        $formData = [
            'names' => [
                'Jane',
                'Bob',
                'Mary',
            ],
            'emails' => [
                'jane@example.com',
                'bob@example.com',
                'mary@example.com',
            ],
            'occupations' => [
                'Doctor',
                'Plumber',
                'Dentist',
            ],
        ];

        //Must take and return a Collection
        $transpose = function (Collection $collections) {
            $transposed = \array_map(
                function (...$items) {
                    return $items;
                },
                ...$collections->values()->toArray()
            );

            return new Collection($transposed);
        };

        $result = (new Collection($formData))
            ->transform($transpose)
            ->toArray();

        $expected = [
            [
                'Jane',
                'jane@example.com',
                'Doctor',
            ],
            [
                'Bob',
                'bob@example.com',
                'Plumber',
            ],
            [
                'Mary',
                'mary@example.com',
                'Dentist',
            ],
        ];

        $this->assertEquals($expected, $result);
    }
}
