<?php

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
     * Find if one expression of a list matches with a current subject
     * (Regular expressions are allowed).
     *
     * @param array $list
     * @param string $subject
     * @return bool
     */
    public static function isExpressionFound(array $list, string $subject) : bool
    {

        foreach ($list as $filter_value)
        {

            if ($filter_value[0] != '/')
            {
                $filter_value = '/' . $filter_value;

                if ($filter_value[strlen($filter_value) - 1] != '/')
                    $filter_value .= '/';
            }

            if (preg_match($filter_value, $subject) === 1)
                return true;

        }

        return false;

    }

}