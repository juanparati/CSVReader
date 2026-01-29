<?php

declare(strict_types=1);

namespace Juanparati\CsvReader;

use Juanparati\CsvReader\Contracts\CsvFieldMap;
use Juanparati\CsvReader\Exceptions\CsvFileException;
use Juanparati\CsvReader\FieldMaps\CsvFieldAuto;
use Juanparati\CsvReader\Helpers\EncodingInfo;

/**
 * Class CSVReader.
 *
 * Parse and read CSV files as a stream keeping a low memory footprint.
 */
class CsvReader
{
    /**
     * The base charset
     */
    public const string BASE_CHARSET = 'UTF-8';

    /**
     * Common delimiters.
     */
    public const string DELIMITER_SEMICOLON = ';';
    public const string DELIMITER_COMMA = ',';
    public const string DELIMITER_PIPE = '|';
    public const string DELIMITER_TAB = "\t";
    public const string DELIMITER_CARET = '^';
    public const string DELIMITER_AMPERSAND = '&';


    /**
     * Common string enclosures.
     */
    public const string ENCLOSURE_TILDES = '~';
    public const string ENCLOSURE_QUOTES = '"';
    public const string ENCLOSURE_NONE = "\010";    // Using backspace as a replacement


    /**
     * Column -> Field mapping.
     *
     * @var CsvFieldMap[]
     */
    protected array $fieldMaps = [];


    /**
     * File pointer resource.
     *
     * @var false|resource
     */
    protected mixed $fp;


    /**
     * Encoding information.
     *
     * @var array
     */
    protected array $encodingInfo = [
        'bom'        => null,
        'bom_length' => 0,
        'charset'    => self::BASE_CHARSET
    ];


    /**
     * CSVReader constructor.
     *
     * @param string $file Path to the CSV file
     * @param string $delimiter Column delimiter
     * @param string $enclosureChar Enclosure character
     * @param string|null $charset Encoding charset
     * @param string $escapeChar Escape character
     * @param string $excludeField Field name used to flag excluded rows
     * @param array $streamFilters Stream filters
     * @throws CsvFileException
     */
    public function __construct(
        protected string $file,
        protected string $delimiter = self::DELIMITER_SEMICOLON,
        protected string $enclosureChar = self::ENCLOSURE_QUOTES,
        ?string $charset = null,
        protected string $escapeChar = '\\',
        protected string $excludeField = 'exclude',
        protected array  $streamFilters = []
    ) {
        $this->enclosureChar = $this->enclosureChar ?: static::ENCLOSURE_NONE;
        $this->fp            = @fopen($this->file, "r");

        if ($this->fp === false) {
            throw new CsvFileException('Unable to read CSV file: ' . $this->file);
        }

        // Apply stream filters
        array_walk(
            $this->streamFilters,
            fn ($r) => $r->setFp($this->fp)->apply($this->fp)
        );

        // Detect encoding and BOM (read 4 bytes to detect all BOM types)
        $this->encodingInfo = EncodingInfo::getInfo(fread($this->fp, 4));

        // In case that we want to enforce a specific charset
        if ($charset) {
            $this->encodingInfo['charset'] = $charset;
        }

        // For UTF-16/UTF-32, we need to apply a stream filter for proper conversion
        if ($this->encodingInfo['charset'] !== static::BASE_CHARSET) {
            $this->applyUTFStreamFilter();
        } else {
            // For UTF-8, reset to beginning and skip BOM if present
            rewind($this->fp);
            if ($this->encodingInfo['bom_length'] > 0) {
                fseek($this->fp, $this->encodingInfo['bom_length']);
            }
        }
    }


    /**
     * Factory method that infers the CSV format from the file and open it.
     *
     * @param string $file
     * @param string $excludeField
     * @param array $streamFilters
     * @return CsvReader
     * @throws CsvFileException
     */
    public static function open(
        string $file,
        string $excludeField = 'exclude',
        array $streamFilters = []
    ): CsvReader
    {
        $csvDetect = (new CsvAutoDetector($file))->detect();

        return new static(
            $file,
            $csvDetect['delimiter'],
            $csvDetect['enclosure'],
            $csvDetect['charset'],
            $csvDetect['escapeChar'],
            $excludeField,
            $streamFilters
        );
    }


    /**
     * Destructor.
     */
    public function __destruct()
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
        }
    }


    /**
     * Set the field mapping (Used with CSV that have header columns).
     *
     * @param CsvFieldMap[] $fields
     * @param int $headerRow
     * @return CsvReader
     */
    public function setMapFields(array $fields, int $headerRow = 0): static
    {
        // Reset field maps and properties
        $this->fieldMaps = [];

        // Reset pointer position
        $this->seekLine($headerRow);

        $columns = $this->getNextLine() ?: [];

        $columns = array_filter(
            $columns,
            fn($r) => $r !== '' && $r !== null,
        );

        // Ignore the empty header line
        if (empty($columns)) {
            return $this;
        }

        // Maps fields
        foreach ($fields as $k => $field) {
            if (!($field instanceof CsvFieldMap)) {
                throw new \RuntimeException('Invalid field mapping for key: ' . $k);
            }

            if (is_int($field->srcField)) {
                $this->fieldMaps[$k] = $field;
            } else {
                $columnNum = array_search($field->srcField, $columns);

                if ($columnNum !== false) {
                    $field->srcField     = $columnNum;
                    $this->fieldMaps[$k] = $field;
                }
            }
        }

        return $this;
    }


    /**
     * Automatically map fields according to the headers.
     *
     * @param int $headerRow
     * @return static
     */
    public function setAutomaticMapField(int $headerRow = 0): static
    {
        $this->seekLine($headerRow);

        $headers = $this->getNextLine();

        // Ignore the empty header line
        if (empty($headers)) {
            return $this;
        }

        $map = [];

        foreach ($headers as $header) {
            $map[$header] = new CsvFieldAuto($header);
        }

        return $this->setMapFields($map, $headerRow);
    }

    /**
     * Get the field mapping.
     */
    public function getFieldMaps(): array
    {
        return $this->fieldMaps;
    }

    /**
     * Export field mapping.
     *
     * @return array
     */
    public function exportFieldMaps(): array
    {
        return array_map(
            fn ($map) => $map->jsonSerialize(),
            $this->fieldMaps
        );
    }


    /**
     * Import field mapping from an array.
     *
     * @param array $maps
     * @return $this
     */
    public function importFieldMaps(array $maps): static
    {
        $fields = [];

        foreach ($maps as $k => $map) {
            if (!isset($map['class'])) {
                throw new \RuntimeException('Invalid field mapping for key: ' . $k);
            }

            $fields[$k] = call_user_func([$map['class'], 'make'], $map);
        }

        $this->setMapFields($fields);

        return $this;
    }


    /**
     * Read entire data from the CSV and return it structured according to the map fields.
     * It is not recommended to use this method with larges CSV files.
     *
     * @param int $headerRow
     * @return array
     */
    public function readAll(int $headerRow = 1): array
    {
        $this->seekLine($headerRow);

        $records = [];

        // Read CSV
        while (($row = $this->readLine())) {
            $records[] = $row;
        }

        return $records;
    }


    /**
     * Read CSV data as a generator for memory-efficient processing.
     * This method yields rows one at a time, maintaining a low memory footprint.
     *
     * Example usage:
     * ```php
     * foreach ($csv->read() as $row) {
     *     // Process row
     * }
     * ```
     *
     * @param int $headerRow Starting row (default: 1 to skip header)
     * @param bool $skipEmpty Skip empty lines (default: true)
     * @return \Generator
     */
    public function read(int $headerRow = 1, bool $skipEmpty = true): \Generator
    {
        $this->seekLine($headerRow);

        while (($row = $this->readLine()) !== false) {
            // Skip empty lines if requested
            if ($skipEmpty && $row === true) {
                continue;
            }

            yield $row;
        }
    }


    /**
     * Read the CSV file line by line.
     *
     * @return array|bool
     */
    public function readLine(): array|bool
    {
        $columns = $this->getNextLine();

        if (!$columns) {
            return false;
        }

        // Convert empty values to null
        $allEmpty = true;

        // ⚡️ Do not user array_map, it's slower than foreach
        foreach ($columns as &$column) {
            if ($column === '' || $column === null) {
                $column = null;
                continue;
            }

            $allEmpty = false;
        }

        // Detect empty lines
        if ($allEmpty) {
            return true;
        }

        $from = [];

        if (empty($this->fieldMaps)) {
            $from = $columns;
        } else {
            foreach ($this->fieldMaps as $k => $columnMap) {

                if (!isset($columns[$columnMap->srcField])) {
                    $from[$k] = null;
                    continue;
                }

                $value = $columns[$columnMap->srcField];

                $value = $columnMap->transform($value);

                if ($columnMap->shouldBeFiltered($value)) {
                    return false;
                }

                if ($columnMap->shouldBeExclude($value)) {
                    $from[$this->excludeField] = true;
                }

                $from[$k] = $value;
            }
        }

        return $from;
    }


    /**
     * Seek the file pointer to a specific line.
     *
     * @param int $line
     * @return bool
     */
    public function seekLine(int $line): bool
    {
        // Reset file pointer position
        rewind($this->fp);

        // Ignore BOM sequence
        // @see: https://en.wikipedia.org/wiki/Byte_order_mark
        if ($line === 0
            && $this->encodingInfo['bom']
            && $this->encodingInfo['bom_length'] > 0) {
            fseek($this->fp, $this->encodingInfo['bom_length']);
        }

        $current = 0;

        do {
            if ($line === $current) {
                return true;
            }

            $current++;

        } while (fgets($this->fp) !== false);

        return false;
    }


    /**
     * Get file pointer position in bytes.
     *
     * @see http://php.net/manual/en/function.ftell.php
     * @return bool|int
     */
    public function tellPosition(): int|false
    {
        return ftell($this->fp);
    }


    /**
     * Get encoding information and file stats.
     *
     * @return array[encoding:array,file:array]
     */
    public function info(): array
    {
        return [
            'encoding' => $this->encodingInfo,
            'file'     => fstat($this->fp),
        ];
    }

    /**
     * Read CSV line.
     *
     * @return array|false
     */
    protected function getNextLine(): array|false
    {
        return fgetcsv($this->fp, 0, $this->delimiter, $this->enclosureChar, $this->escapeChar);
    }


    /**
     * Apply UTF-16/UTF-32 stream filter for automatic conversion.
     * This enables reading UTF-16/UTF-32 files as if they were UTF-8.
     *
     * @return void
     */
    protected function applyUTFStreamFilter(): void
    {
        // Reset to beginning and skip BOM
        rewind($this->fp);

        // Skip BOM sequence
        if ($this->encodingInfo['bom_length'] > 0) {
            fseek($this->fp, $this->encodingInfo['bom_length']);
        }

        // Apply the appropriate iconv stream filter
        // This converts UTF-16/UTF-32 to UTF-8 on-the-fly as we read
        $filterName = "convert.iconv.{$this->encodingInfo['charset']}." . static::BASE_CHARSET;

        stream_filter_append($this->fp, $filterName, STREAM_FILTER_READ);
    }
}
