<?php

namespace Juanparati\CSVReader\FieldMaps;

use Juanparati\CSVReader\Enums\CsvFieldCast;

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
