<?php

namespace Juanparati\CSVReader\Enums;


enum BomType : string
{
    /**
     * BOM types and their byte sequences.
     */
    case UTF8 = 'UTF-8';
    case UTF16_LE = 'UTF-16LE';
    case UTF16_BE = 'UTF-16BE';
    case UTF32_LE = 'UTF-32LE';
    case UTF32_BE = 'UTF-32BE';


    protected static function signatures() : array
    {
        return [
            // UTF-32 (4 bytes) - check first
            "\x00\x00\xFE\xFF" => self::UTF32_BE,
            "\xFF\xFE\x00\x00" => self::UTF32_LE,
            // UTF-8 (3 bytes)
            "\xEF\xBB\xBF"     => self::UTF8,
            // UTF-16 (2 bytes) - check last
            "\xFE\xFF"         => self::UTF16_BE,
            "\xFF\xFE"         => self::UTF16_LE,
        ];
    }


    /**
     * Return the signature for the BOM type.
     *
     * @return string
     */
    public function signature() : string
    {
        foreach (self::signatures() as $signature => $type) {
            if ($type === $this) {
                return $signature;
            }
        }

        return '';
    }

    /**
     * Get the BOM byte length for a specific encoding.
     *
     * @return int The number of bytes in the BOM
     */
    public function length(): int
    {
        return strlen($this->signature());
    }

    /**
     * Detect BOM type from the beginning of a string.
     * Returns the encoding type or null if no BOM detected.
     *
     * @param string $string First few bytes of the file (at least 4 bytes recommended)
     * @return BomType|null The BOM type constant or null
     */
    public static function detectBom(string $string): ?BomType
    {
        foreach (self::signatures() as $signature => $type) {
            $length = strlen($signature);
            if (substr($string, 0, $length) === $signature) {
                return $type;
            }
        }

        return null;
    }


}
