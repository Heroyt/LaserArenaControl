<?php

declare(strict_types=1);

namespace App\Cli;

use function array_merge;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function ksort;

/**
 * @internal
 */
final class ParametersSorter
{
    private const
      TYPE_ARRAY = 'array',
      TYPE_BOOL = 'bool',
      TYPE_NUMBER = 'number',
      TYPE_STRING = 'string',
      TYPE_NULL = 'null',
      TYPE_OTHER = 'other';

    /**
     * @param  array<mixed>  $parameters
     * @return array<mixed>
     */
    public static function sortByType(array $parameters) : array {
        ksort($parameters);
        $byType = [
          self::TYPE_ARRAY  => [],
          self::TYPE_BOOL   => [],
          self::TYPE_NUMBER => [],
          self::TYPE_STRING => [],
          self::TYPE_NULL   => [],
          self::TYPE_OTHER  => [],
        ];

        foreach ($parameters as $key => $item) {
            if (is_array($item)) {
                $item = self::sortByType($item);
                $type = self::TYPE_ARRAY;
            }
            else if (is_bool($item)) {
                $type = self::TYPE_BOOL;
            }
            else if (is_int($item) || is_float($item)) {
                $type = self::TYPE_NUMBER;
            }
            else if (is_string($item)) {
                $type = self::TYPE_STRING;
            }
            else if ($item === null) {
                $type = self::TYPE_NULL;
            }
            else {
                $type = self::TYPE_OTHER;
            }

            $byType[$type][$key] = $item;
        }

        return array_merge(
          $byType[self::TYPE_BOOL],
          $byType[self::TYPE_STRING],
          $byType[self::TYPE_NUMBER],
          $byType[self::TYPE_NULL],
          $byType[self::TYPE_OTHER],
          $byType[self::TYPE_ARRAY],
        );
    }
}
