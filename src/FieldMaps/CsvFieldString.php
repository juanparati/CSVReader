<?php

namespace Juanparati\CsvReader\FieldMaps;

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
