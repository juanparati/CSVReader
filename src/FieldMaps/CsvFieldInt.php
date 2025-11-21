<?php

namespace Juanparati\CSVReader\FieldMaps;

/**
 * Auto cast fields.
 */
class CsvFieldInt extends CsvFieldMapBase
{
    public function transform(mixed $value): int
    {
        return (int) parent::transform($value);
    }
}
