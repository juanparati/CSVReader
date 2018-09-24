<?php

namespace Juanparati\CSVReader\Helpers;


/**
 * Class Sanitize.
 *
 * Sensitization helper methods
 *
 * @package Juanparati\CSVReader
 */
class Sanitize
{

    /**
     * Check if value is currency type and extract the value.
     *
     * @param mixed $value
     * @param string $decimal_point
     * @param float|null $min_bound     Maximum value accepted
     * @param float|null $max_bound     Minimum value accepted
     * @return bool|mixed
     */
    public static function extractCurrency(
        $value,
        string $decimal_point = '.',
        float $min_bound = null,
        float $max_bound = null)
    {
        // Check if more than one decimal point is present
        $matched = 0;
        $purevalue = str_replace($decimal_point, '', $value, $matched);

        if ($matched > 1)
            return false;

        // Check if value is a valid pure integer value when decimal points are removed
        if (!ctype_digit($purevalue))
            return false;

        // Convert decimal value if proceed
        if ($decimal_point != '.')
            $value = str_replace($decimal_point, '.', $value);

        // Check if between the bounds
        if ($min_bound != null && $value < $min_bound)
            return false;

        if ($max_bound != null && $value > $max_bound)
            return false;

        return $value;
    }

}