<?php

declare(strict_types=1);

namespace Juanparati\CSVReader\Helpers;


/**
 * Class Arr.
 *
 * Array helper methods.
 *
 * @package Juanparati\CSVReader
 */
class Arr
{
    /**
     * Compile a pattern string into a valid regex pattern.
     *
     * @param string $pattern
     * @return string
     */
    public static function compilePattern(string $pattern): string
    {
        if ($pattern[0] !== '/') {
            $pattern = '/' . $pattern;

            if ($pattern[strlen($pattern) - 1] !== '/') {
                $pattern .= '/';
            }
        }

        return $pattern;
    }

    /**
     * Pre-compile an array of patterns into valid regex patterns.
     * ⚡️ Optimization: Use this method to compile patterns once instead of on every check.
     *
     * @param array $patterns
     * @return array
     */
    public static function compilePatterns(array $patterns): array
    {
        return array_map([self::class, 'compilePattern'], $patterns);
    }

    /**
     * Find if one expression of a list matches with a current subject
     * (Regular expressions are allowed).
     *
     * @param array $list List of patterns (can be pre-compiled or raw)
     * @param string $subject
     * @param bool $precompiled If true, assumes patterns are already compiled
     * @return bool
     */
    public static function isExpressionFound(array $list, string $subject, bool $precompiled = false): bool
    {
        foreach ($list as $filterValue) {
            // ⚡️ Optimization: Skip pattern compilation if already compiled
            if (!$precompiled) {
                $filterValue = self::compilePattern($filterValue);
            }

            if (preg_match($filterValue, $subject) === 1) {
                return true;
            }
        }

        return false;
    }
}