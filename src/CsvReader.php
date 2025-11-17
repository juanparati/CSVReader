<?php
declare(strict_types=1);

namespace Juanparati\CSVReader;

use Juanparati\CSVReader\Exceptions\CsvFileException;
use Juanparati\CSVReader\Helpers\BomString;
use Juanparati\CSVReader\Helpers\Sanitize;
use Juanparati\CSVReader\Helpers\Arr;


/**
 * Class CSVReader.
 *
 * Parse and read CSV files as a stream keeping a low memory footprint.
 */
class CsvReader
{

    /**
     * Common delimiters.
     */
    public const string DELIMITER_SEMICOLON = ';';
    public const string DELIMITER_COMMA     = ',';
    public const string DELIMITER_PIPE      = '|';
    public const string DELIMITER_TAB       = "\t";
    public const string DELIMITER_CARET     = '^';
    public const string DELIMITER_AMPERSAND = '&';


    /**
     * Common string enclosures.
     */
    public const string ENCLOSURE_TILDES = '~';
    public const string ENCLOSURE_QUOTES = '"';
    public const string ENCLOSURE_NONE   = "\010";    // Using backspace as replacement


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
     * Column -> Field name map.
     */
    protected array $fieldmap = [];


    /**
     * Field properties (Only when a field map is used).
     */
    protected array $fieldProps = [];


    /**
     * Indicates if the CSV file needs to be encoded.
     *
     * @var bool
     */
    protected bool $needsEncoding = false;


    /**
     * Cached static values for field mapping.
     *
     * @var array
     */
    protected array $staticValues = [];


    /**
     * Pre-compiled exclusion patterns for performance.
     *
     * @var array
     */
    protected array $compiledExcludePatterns = [];


    /**
     * Detected BOM type (if any).
     *
     * @var string|null
     */
    protected ?string $bomType = null;


    /**
     * Number of bytes to skip for BOM.
     *
     * @var int
     */
    protected int $bomLength = 0;


    /**
     * CSVReader constructor.
     *
     * @param string $file
     * @param string $delimiter
     * @param string $enclosureChar
     * @param string $charset CSV charset encoding
     * @param string $decimalSep
     * @param string $escapeChar
     * @param bool $hasBom
     * @param CsvStreamFilter[] $streamFilters
     * @throws CsvFileException
     */
    public function __construct(
        protected string $file,
        protected string $delimiter = ';',
        protected string $enclosureChar = '"',
        protected string $charset = 'UTF-8',
        protected string $decimalSep = ',',
        protected string $escapeChar = '\\',
        protected bool $hasBom = false,
        protected array $streamFilters = []
    )
    {
        $this->fp = fopen($this->file, "r");

        if ($this->fp === false) {
            throw new CsvFileException('Unable to read CSV file: ' . $this->file);
        }

        // Apply stream filters
        array_walk(
            $this->streamFilters,
            fn($r) => $r->setFp($this->fp)->apply($this->fp)
        );

        $this->enclosureChar = empty($enclosureChar) ? chr(8) : $enclosureChar;

        // Detect BOM type (read 4 bytes to detect all BOM types)
        $bomBytes = fread($this->fp, 4);
        $this->bomType = BomString::detectBOM($bomBytes);

        if ($this->bomType !== null) {
            $this->hasBom = true;
            $this->bomLength = BomString::getBOMLength($this->bomType);

            // Auto-detect charset from BOM if not explicitly set or if UTF-8 was default
            if ($this->charset === 'UTF-8') {
                $this->charset = BomString::getCharset($this->bomType);
            }
        }

        // Detect if it needs encoding
        $this->needsEncoding = ($this->charset !== 'UTF-8');

        // For UTF-16, we need to apply a stream filter for proper conversion
        if (str_starts_with($this->charset, 'UTF-16')) {
            $this->applyUTF16StreamFilter();
        }
    }


    /**
     * Destructor.
     */
    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }


    /**
     * Set the field mapping (Used with CSV that have header columns).
     *
     * @param array $fields
     * @param int $headerRow
     * @return CsvReader
     */
    public function setMapField(array $fields, int $headerRow = 0) : static
    {
        // Reset fieldmap and properties
        $this->fieldmap    = [];
        $this->fieldProps = [];
        $this->staticValues = [];
        $this->compiledExcludePatterns = [];

        // Reset pointer position
        if ($headerRow !== false) {
            $this->seekLine($headerRow);
        }

        $columns = $this->readCSVLine();

        // Ignore the empty header line
        if (empty($columns))
            return $this;

        // Ignore lines with less than 2 columns
        if (count($columns) < 2)
            return $this;

        // Encode columns
        // ⚡️ Optimization: "encode" method already checks if it needs encoding, but for performance reasons
        // we ignore the method call when encoding is not required.
        if ($this->needsEncoding) {
            $columns = array_map(fn($r) => $this->encode($r), $columns);
        }

        // Map fields
        foreach ($fields as $k => $field) {
            if ($field === false || !isset($field['column'])) {
                continue;
            }

            if (is_int($field['column'])) {
                $this->fieldmap[$k] = $field['column'];
            } else {
                $this->fieldmap[$k] = array_search($field['column'], $columns);
            }
        }

        $this->fieldProps = $fields;

        // ⚡️ Optimization: Pre-cache static values and compile exclusion patterns
        foreach ($fields as $k => $props) {
            if (!is_array($props)) {
                continue;
            }

            // Cache static values
            if (array_key_exists('static_value', $props)) {
                $this->staticValues[$k] = $props['static_value'];
            }

            // Pre-compile exclusion patterns
            if (!empty($props['exclude'])) {
                $this->compiledExcludePatterns[$k] = Arr::compilePatterns($props['exclude']);
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
    public function setAutomaticMapField(int $headerRow = 0) : static
    {
        $this->seekLine($headerRow);

        $headers = $this->readCSVLine();

        // Ignore the empty header line
        if (empty($headers))
            return $this;

        $map = [];

        foreach ($headers as $header) {
            $map[$header]['column'] = $header;
        }

        return $this->setMapField($map, $headerRow);
    }


    /**
     * Read entire data from the CSV and return it structured according to the map fields.
     * It is not recommended to use this method with larges CSV files.
     *
     * @param int $headerRow
     * @return array
     */
    public function read(int $headerRow = 1) : array
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
     * Use this method instead of read() for large CSV files.
     * This method yields rows one at a time, maintaining a low memory footprint.
     *
     * Example usage:
     * ```php
     * foreach ($csv->readGenerator() as $row) {
     *     // Process row
     * }
     * ```
     *
     * @param int $headerRow Starting row (default: 1 to skip header)
     * @param bool $skipEmpty Skip empty lines (default: true)
     * @return \Generator
     */
    public function readGenerator(int $headerRow = 1, bool $skipEmpty = true): \Generator
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
        $columns = $this->readCSVLine();

        if (!$columns) {
            return false;
        }

        // Detect empty lines
        if (count($columns) === 1) {
            return true;
        }

        $frow = [];

        if (empty($this->fieldmap)) {
            $frow[] = $columns;
        } else {
            foreach ($this->fieldmap as $k => $columnmap) {

                if (isset($columns[$columnmap])) {
                    $value = $columns[$columnmap];

                    // Remove characters
                    if (!empty($this->fieldProps[$k]['remove'])) {
                        $value = str_replace($this->fieldProps[$k]['remove'], '', $value);
                    }

                    // Replace characters
                    if (!empty($this->fieldProps[$k]['replace'])) {
                        $value = strtr($value, $this->fieldProps[$k]['replace']);
                    }

                    // Extract word segments
                    if (isset($this->fieldProps[$k]['segment']) && is_int($this->fieldProps[$k]['segment'])) {
                        $segments = explode(' ', $value);
                        $value = empty($segments[$this->fieldProps[$k]['segment']]) ? '' : $segments[$this->fieldProps[$k]['segment']];
                    }

                    // Cast
                    if (!empty($this->fieldProps[$k]['cast'])) {
                        $value = match ($this->fieldProps[$k]['cast']) {
                            'int', 'integer' => (int) $value,
                            'float' => (float) $value,
                            'string' => (string) $value,
                            default => $value,
                        };
                    }

                    // Apply exclusion list
                    // ⚡️ Optimization: Use pre-compiled patterns to avoid regex compilation on every row
                    if (!empty($this->compiledExcludePatterns[$k])) {
                        if (Arr::isExpressionFound($this->compiledExcludePatterns[$k], $value, true)) {
                            $frow['exclude'] = true;
                        }
                    }

                    // Convert decimal values
                    $currency = Sanitize::extractCurrency($value, $this->decimalSep);

                    // Save value or string
                    $frow[$k] = $currency === false ? $this->encode($value) : $currency;

                }

            }

            // Set static values
            // ⚡️ Optimization: Use cached static values instead of iterating through field props
            if (!empty($this->staticValues)) {
                $frow = array_merge($frow, $this->staticValues);
            }
        }


        return $frow;
    }


    /**
     * Seek the file pointer to a specific line.
     *
     * @param int $line
     * @return bool
     */
    public function seekLine(int $line) : bool
    {
        // Reset file pointer position
        rewind($this->fp);

        // Ignore BOM sequence
        if ($this->hasBom && $this->bomLength > 0) {
            fseek($this->fp, $this->bomLength);
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
     * Get file information.
     *
     * @see http://php.net/manual/en/function.fstat.php
     * @return array
     */
    public function info() : array
    {
        return fstat($this->fp);
    }

    /**
     * Read CSV line.
     *
     * @return array|false|null
     */
    protected function readCSVLine(): array|false|null
    {
        return fgetcsv($this->fp, 0, $this->delimiter, $this->enclosureChar,  $this->escapeChar);
    }


    /**
     * Encode a text to UTF-8.
     *
     * @param string $text
     * @return string
     */
    protected function encode(string $text): string
    {
        if ($this->needsEncoding === false) {
            return $text;
        }

        return mb_convert_encoding($text, 'UTF-8', $this->charset);
    }


    /**
     * Apply UTF-16 stream filter for automatic conversion.
     * This enables reading UTF-16 files as if they were UTF-8.
     *
     * @return void
     */
    protected function applyUTF16StreamFilter(): void
    {
        // Reset to beginning and skip BOM
        rewind($this->fp);
        if ($this->bomLength > 0) {
            fseek($this->fp, $this->bomLength);
        }

        // Apply the appropriate iconv stream filter
        // This converts UTF-16 to UTF-8 on-the-fly as we read
        $filterName = match ($this->charset) {
            'UTF-16LE' => 'convert.iconv.UTF-16LE.UTF-8',
            'UTF-16BE' => 'convert.iconv.UTF-16BE.UTF-8',
            default => null,
        };

        if ($filterName !== null) {
            stream_filter_append($this->fp, $filterName, STREAM_FILTER_READ);
            // After applying the filter, we're reading UTF-8, so disable encoding
            $this->needsEncoding = false;
        }
    }


    /**
     * Get detected BOM information.
     *
     * @return array{type: string|null, length: int, charset: string|null}
     */
    public function getBOMInfo(): array
    {
        return [
            'type' => $this->bomType,
            'length' => $this->bomLength,
            'charset' => $this->bomType !== null ? BomString::getCharset($this->bomType) : null,
        ];
    }


}
