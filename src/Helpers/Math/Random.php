<?php

declare(strict_types=1);

namespace App\Helpers\Math;

use Random\Randomizer;

class Random
{
    /**
     * Generate a random value based on a normal distribution
     *
     * @param  float  $median
     * @param  float  $stdDeviation
     * @return int
     */
    public static function randomNormal(float $median, float $stdDeviation) : int {
        $randomizer = new Randomizer();

        // Generate two random numbers between 0 and 1
        $num1 = $randomizer->nextFloat();
        $num2 = $randomizer->nextFloat();

        // Calculate the standard normal deviation
        $z = sqrt(-2 * log($num1)) * cos(2 * M_PI * $num2);

        // Scale and shift the standard normal deviation to get the desired median and standard deviation
        $value = (int) round($median + ($stdDeviation * $z));

        // Make sure that the value is not negative
        if ($value < 0) {
            return 0;
        }
        return $value;
    }

    /**
     * Generate a random distribution of N values that sum up to a given value
     *
     * @param  int  $sum
     * @param  int  $count
     * @return int[]
     */
    public static function randomSumDistribution(int $sum, int $count) : array {
        if ($count < 2) {
            return [$sum];
        }

        $randomizer = new Randomizer();

        // Generate N-1 random partition points
        $partition_points = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $partition_points[] = $randomizer->getInt(0, $sum);
        }
        sort($partition_points);

        // Compute the N parts
        $parts = [];
        $parts[] = $partition_points[0];
        for ($i = 1; $i < $count - 1; $i++) {
            $parts[] = $partition_points[$i] - $partition_points[$i - 1];
        }
        $parts[] = $sum - $partition_points[$count - 2];

        return $parts;
    }
}
