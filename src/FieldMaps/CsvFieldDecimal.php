<?php

namespace Juanparati\CSVReader\FieldMaps;

/**
 * Auto cast fields.
 */
class CsvFieldDecimal extends CsvFieldMapBase
{

    /**
     * Common decimal separators.
     */
    public const string DECIMAL_SEP_POINT = '.';
    public const string DECIMAL_SEP_COMMA = ',';
    public const string DECIMAL_SEP_APOSTROPHE = "'";
    public const string DECIMAL_SEP_APOSTROPHE_9995 = '⎖';
    public const string DECIMAL_SEP_UNDERSCORE = '_';
    public const string DECIMAL_SEP_ARABIC = '٫';

    /**
     * Constructor.
     *
     * @param int|string $srcField
     * @param string $decimalSeparator
     */
    public function __construct(
        int|string $srcField,
        protected string $decimalSeparator = self::DECIMAL_SEP_POINT
    ) {
        parent::__construct($srcField);
    }


    public function transform(mixed $value): int
    {
        $value = parent::transform($value);

        if ($value !== static::DECIMAL_SEP_POINT) {
            $value = str_replace($this->decimalSeparator, static::DECIMAL_SEP_POINT, $value);
        }

        return (float) $value;
    }

    public function jsonSerialize(): array
    {
        return parent::jsonSerialize() + [
            'decimalSeparator' => $this->decimalSeparator
        ];
    }
}
