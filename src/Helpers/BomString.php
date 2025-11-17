<?php

declare(strict_types=1);

namespace Juanparati\CSVReader\Helpers;


class BomString
{
    /**
     * BOM types and their byte sequences.
     */
    public const string BOM_UTF8 = 'UTF-8';
    public const string BOM_UTF16_LE = 'UTF-16LE';
    public const string BOM_UTF16_BE = 'UTF-16BE';
    public const string BOM_UTF32_LE = 'UTF-32LE';
    public const string BOM_UTF32_BE = 'UTF-32BE';

    /**
     * BOM byte sequences mapped to encoding type.
     * Order matters - check longer sequences first!
     */
    private const array BOM_SIGNATURES = [
        // UTF-32 (4 bytes) - check first
        "\x00\x00\xFE\xFF" => self::BOM_UTF32_BE,
        "\xFF\xFE\x00\x00" => self::BOM_UTF32_LE,
        // UTF-8 (3 bytes)
        "\xEF\xBB\xBF"     => self::BOM_UTF8,
        // UTF-16 (2 bytes) - check last
        "\xFE\xFF"         => self::BOM_UTF16_BE,
        "\xFF\xFE"         => self::BOM_UTF16_LE,
    ];

    /**
     * Check if string has UTF-8 BOM sequence.
     *
     * @see   https://github.com/emrahgunduz/bom-cleaner/blob/master/bom.php
     * @param string $string
     * @return bool
     */
    public static function hasBOM(string $string): bool
    {
        return substr($string, 0, 3) === "\xEF\xBB\xBF";
    }

    /**
     * Detect BOM type from the beginning of a string.
     * Returns the encoding type or null if no BOM detected.
     *
     * @param string $string First few bytes of the file (at least 4 bytes recommended)
     * @return string|null The BOM type constant or null
     */
    public static function detectBOM(string $string): ?string
    {
        foreach (self::BOM_SIGNATURES as $signature => $type) {
            $length = strlen($signature);
            if (substr($string, 0, $length) === $signature) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Get the BOM byte length for a specific encoding.
     *
     * @param string $bomType One of the BOM_* constants
     * @return int The number of bytes in the BOM
     */
    public static function getBOMLength(string $bomType): int
    {
        return match ($bomType) {
            self::BOM_UTF8 => 3,
            self::BOM_UTF16_LE, self::BOM_UTF16_BE => 2,
            self::BOM_UTF32_LE, self::BOM_UTF32_BE => 4,
            default => 0,
        };
    }

    /**
     * Strip BOM from string if present.
     *
     * @param string $string
     * @return string String without BOM
     */
    public static function stripBOM(string $string): string
    {
        $bomType = self::detectBOM($string);

        if ($bomType === null) {
            return $string;
        }

        return substr($string, self::getBOMLength($bomType));
    }

    /**
     * Get the actual charset name for PHP's mb_convert_encoding.
     *
     * @param string $bomType One of the BOM_* constants
     * @return string The charset name
     */
    public static function getCharset(string $bomType): string
    {
        return match ($bomType) {
            self::BOM_UTF8 => 'UTF-8',
            self::BOM_UTF16_LE => 'UTF-16LE',
            self::BOM_UTF16_BE => 'UTF-16BE',
            self::BOM_UTF32_LE => 'UTF-32LE',
            self::BOM_UTF32_BE => 'UTF-32BE',
            default => 'UTF-8',
        };
    }
}