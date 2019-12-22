<?php

namespace Truonglv\Api\Util;

class BinarySearch
{
    /**
     * @param array $numbers
     * @param float $target
     * @return float
     */
    public static function findClosestNumber(array $numbers, $target)
    {
        $total = \count($numbers);
        if ($total === 0) {
            throw new \InvalidArgumentException('Array numbers must not be empty!');
        }

        if ($target <= $numbers[0]) {
            return $numbers[0];
        } elseif ($target >= $numbers[$total - 1]) {
            return $numbers[$total - 1];
        }

        $i = 0;
        $j = $total;
        $mid = 0;

        while ($i < $j) {
            $mid = ($i + $j) / 2;
            if ($numbers[$mid] == $target) {
                return $numbers[$mid];
            }

            if ($target < $numbers[$mid]) {
                if ($mid > 0 && $target > $numbers[$mid - 1]) {
                    return self::getClosestValue($numbers[$mid - 1], $numbers[$mid], $target);
                }

                $j = $mid;
            } else {
                if ($mid < ($total - 1) && $target < $numbers[$mid + 1]) {
                    return self::getClosestValue($numbers[$mid], $numbers[$mid + 1], $target);
                }

                $i = $mid + 1;
            }
        }

        return $numbers[$mid];
    }

    /**
     * @param float $value1
     * @param float $value2
     * @param float $target
     * @return float
     */
    protected static function getClosestValue($value1, $value2, $target)
    {
        if (($target - $value1) >= ($value2 - $target)) {
            return $value2;
        }

        return $value1;
    }
}
