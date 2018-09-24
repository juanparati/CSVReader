<?php

namespace Juanparati\CSVReader\Helpers;


class BomString
{

    /**
     * Check if string has BOM sequence.
     *
     * @see   https://github.com/emrahgunduz/bom-cleaner/blob/master/bom.php
     * @param string $string
     * @return bool
     */
    public static function hasBOM(string $string)
    {
        return ( substr( $string, 0, 3 ) == pack( "CCC", 0xef, 0xbb, 0xbf ) );
    }

}