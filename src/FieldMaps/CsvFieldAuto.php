<?php

namespace Juanparati\CSVReader\FieldMaps;

/**
 * Auto cast fields.
 */
class CsvFieldAuto extends CsvFieldMapBase
{
    public function transform(mixed $value): mixed
    {
        $value = parent::transform($value);

        if (FILTER_VAR($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        if (FILTER_VAR($value, FILTER_VALIDATE_FLOAT) !== false) {
            return (float) $value;
        }

        if (FILTER_VAR($value, FILTER_VALIDATE_BOOLEAN) !== false) {
            return (bool) $value;
        }

        return $value;
    }
}
