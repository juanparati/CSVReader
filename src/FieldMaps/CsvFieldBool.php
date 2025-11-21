<?php

namespace Juanparati\CSVReader\FieldMaps;

/**
 * Auto cast fields.
 */
class CsvFieldBool extends CsvFieldMapBase
{
    /**
     * Constructor.
     *
     * @param int|string $srcField
     * @param array $trueValues
     */
    public function __construct(int|string $srcField, protected array $trueValues = [1, 'true', 'on'])
    {
        parent::__construct($srcField);
    }


    public function transform(mixed $value): bool
    {
        return in_array(parent::transform($value), $this->trueValues);
    }

}
