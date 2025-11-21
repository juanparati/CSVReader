<?php

namespace Juanparati\CSVReader\FieldMaps;

/**
 * Auto cast fields.
 */
class CsvFieldString extends CsvFieldMapBase
{
    public function transform(mixed $value): string
    {
        return (string) parent::transform($value);
    }
}
