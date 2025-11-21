<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Helpers;

/**
 * Class Sanitize.
 *
 * Sanitization helper methods
 *
 * @package Juanparati\CsvReader
 */
class Sanitize
{
    /**
     * Check if the value is a currency type and extract the value.
     *
     * @param mixed $value
     * @param string $decimalPoint
     * @param float|null $minBound Maximum value accepted
     * @param float|null $maxBound Minimum value accepted
     * @return float|false
     */
    public static function extractCurrency(
        mixed $value,
        string $decimalPoint = '.',
        ?float $minBound = null,
        ?float $maxBound = null
    ): float|false {
        // Check if more than one decimal point is present
        // ⚡️ Optimization: Use substr_count instead of str_replace with count parameter
        if (substr_count((string)$value, $decimalPoint) > 1) {
            return false;
        }

        $pureValue = str_replace($decimalPoint, '', $value);

        // Check if a value is a valid pure integer value when decimal points are removed
        if (!ctype_digit($pureValue)) {
            return false;
        }

        // Convert decimal value if proceed
        if ($decimalPoint !== '.') {
            $value = str_replace($decimalPoint, '.', $value);
        }

        // Check if between the bounds
        if ($minBound !== null && $value < $minBound) {
            return false;
        }

        if ($maxBound !== null && $value > $maxBound) {
            return false;
        }

        return (float) $value;
    }
}
