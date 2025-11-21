<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Helpers;

use Juanparati\CsvReader\CsvReader;
use Juanparati\CsvReader\Enums\BomType;

/**
 * Obtain information about file encoding.
 */
class EncodingInfo
{
    /**
     * Check if string has UTF-8 BOM sequence.
     *
     * @see   https://github.com/emrahgunduz/bom-cleaner/blob/master/bom.php
     * @param string $string
     * @return bool
     */
    public static function hasBom(string $string): bool
    {
        return substr($string, 0, 3) === BomType::UTF8->signature();
    }

    /**
     * Strip BOM from string if present.
     *
     * @param string $string
     * @return string String without BOM
     */
    public static function stripBom(string $string): string
    {
        $bomType = BomType::detectBom($string);

        if ($bomType === null) {
            return $string;
        }

        return substr($string, $bomType->length());
    }

    /**
     * Get BOM info from bytes.
     *
     * @param string $bytes
     * @return array<type:BomType|null,length:int,charset:string>
     */
    public static function getInfo(string $bytes): array
    {
        $bytes = substr($bytes, 0, 4);

        $info = [
            'bom'        => null,
            'bom_length' => 0,
            'charset'    => CsvReader::BASE_CHARSET
        ];

        if ($type = BomType::detectBom($bytes)) {
            $info = [
                'bom'        => $type,
                'bom_length' => $type->length(),
                'charset'    => $type->value
            ];
        }

        return $info;
    }
}
