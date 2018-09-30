<?php
declare(strict_types=1);

namespace Juanparati\CSVReader;

use Juanparati\CSVReader\Exceptions\CSVFileException;
use Juanparati\CSVReader\Helpers\BomString;
use Juanparati\CSVReader\Helpers\Sanitize;
use Juanparati\CSVReader\Helpers\Arr;


/**
 * Class CSVReader.
 *
 * Parse and read CSV files as a stream keeping a low memory footprint.
 */
class CSVReader
{


    /**
     * Common delimiters.
     */
    const DELIMITER_SEMICOLON = ';';
    const DELIMITER_COMMA     = ',';
    const DELIMITER_PIPE      = '|';
    const DELIMITER_TAB       = "\t";
    const DELIMITER_CARET     = '^';
    const DELIMITER_AMPERSAND = '&';


    /**
     * Common string enclosures.
     */
    const ENCLOSURE_TILDES = '~';
    const ENCLOSURE_QUOTES = '"';
    const ENCLOSURE_NONE   = "\010";    // Using backspace as replacement


    /**
     * Common decimal separators.
     */
    const DECIMAL_SEP_POINT = '.';
    const DECIMAL_SEP_COMMA = ',';
    const DECIMAL_SEP_APOSTROPHE = "'";
    const DECIMAL_SEP_APOSTROPHE_9995 = '⎖';


    /**
     * File pointer.
     *
     * @var null|resource   CSV file pointer
     */
    protected $fp = null;


    /**
     * Has BOM.
     *
     * @see https://en.wikipedia.org/wiki/Byte_order_mark
     * @var bool    Indicates if file has BOM sequence
     */
    protected $has_bom = false;


    /**
     * File charset.
     *
     * @var string  CSV charset encoding
     * @link http://php.net/manual/en/function.mb-convert-encoding.php
     */
    protected $charset;


    /**
     * Default decimal separator.
     *
     * @var string
     */
    protected $decimal_sep;


    /**
     * Escape character.
     *
     * @var string
     */
    protected $escape_char;


    /**
     * Column delimiter.
     *
     * @var string  CSV column delimiter
     * @link http://php.net/manual/en/function.fgetcsv.php
     */
    protected $delimiter;


    /**
     * String closure character.
     *
     * @var string
     * @link http://php.net/manual/en/function.fgetcsv.php
     */
    protected $enclosure_char;


    /**
     * Column -> Field name map.
     *
     * @var array
     */
    protected $fieldmap = [];


    /**
     * Field properties (Only when field map is used).
     *
     * @var array
     */
    protected $field_props = [];


    /**
     * Model_CSVReader constructor.
     *
     * @param string $file
     * @param string $delimiter
     * @param string $enclosure_char
     * @param string $charset
     * @param string $decimal_sep
     * @param string $escape_char
     * @throws \Exception
     */
    public function __construct(
        string $file,
        string $delimiter = ';',
        string $enclosure_char = '"',
        string $charset = 'UTF-8',
        string $decimal_sep = ',',
        string $escape_char = '\\'
    )
    {
        // ini_set('auto_detect_line_endings', true);

        $this->fp = fopen($file, "r");

        if (!$this->fp)
            throw new CSVFileException('Unable to read CSV file: ' . $file);

        $this->delimiter    = $delimiter;
        $this->enclosure_char = empty($enclosure_char) ? chr(8) : $enclosure_char;
        $this->charset      = $charset;
        $this->decimal_sep  = $decimal_sep;
        $this->escape_char  = $escape_char;

        // Detect BOM
        $this->has_bom = BomString::hasBOM(fgets($this->fp, 3));
    }


    /**
     * Destructor.
     */
    public function __destruct()
    {
        fclose($this->fp);
    }


    /**
     * Set the field mapping (Used with CSV that have header columns).
     *
     * @param array $fields
     * @param int $header_row
     * @return bool
     */
    public function setMapField(array $fields, int $header_row = 0) : bool
    {

        // Reset fieldmap and properties
        $this->fieldmap    = [];
        $this->field_props = [];

        // Reset pointer position
        if ($header_row !== false)
            $this->seekLine($header_row);

        $columns = $this->readCSVLine();

        // Ignore empty header line
        if (empty($columns))
            return false;

        // Ignore lines with less than 2 columns
        if (count($columns) < 2)
            return false;

        // Encode columns
        $columns = array_map([$this, 'encode'], $columns);

        // Map fields
        foreach ($fields as $k => $field)
        {
            if ($field === false || !isset($field['column']))
                continue;

            if (is_int($field['column']))
                $this->fieldmap[$k] = $field['column'];
            else
                $this->fieldmap[$k] = array_search($field['column'], $columns);
        }

        $this->field_props = $fields;

        return true;
    }


    /**
     * Automatically map fields according to the headers.
     *
     * @param int $header_row
     * @return bool
     */
    public function setAutomaticMapField(int $header_row = 0) : bool
    {
        $this->seekLine($header_row);

        $headers = $this->readCSVLine();

        // Ignore empty header line
        if (empty($headers))
            return false;

        $map = [];

        foreach ($headers as $header)
            $map[$header]['column'] = $header;

        return $this->setMapField($map, $header_row);
    }


    /**
     * Read entire data from the CSV and return it structured according to the map fields.
     * It is not recommended to use this method with larges CSV files.
     *
     * @param int $header_row
     * @return array
     */
    public function read(int $header_row = 1) : array
    {

        $this->seekLine($header_row);

        $records = [];

        // Read CSV
        while(($row = $this->readLine()))
            $records[] = $row;

        return $records;
    }


    /**
     * Read the CSV file line by line.
     *
     * @return array|bool
     */
    public function readLine()
    {

        $columns = $this->readCSVLine();

        if (!$columns)
            return false;

        // Detect empty lines
        if (count($columns) === 1)
            return true;

        $frow = [];

        if (empty($this->fieldmap))
            $frow[] = $columns;
        else
        {
            foreach ($this->fieldmap as $k => $columnmap)
            {

                if (isset($columns[$columnmap]))
                {
                    $value = $columns[$columnmap];

                    // Remove characters
                    if (!empty($this->field_props[$k]['remove']))
                        $value = str_replace($this->field_props[$k]['remove'], '', $value);

                    // Replace characters
                    if (!empty($this->field_props[$k]['replace']))
                    {
                        foreach ($this->field_props[$k]['replace'] as $search_str => $replace_str)
                            $value = str_replace($search_str, $replace_str, $value);
                    }

                    // Extract word segments
                    if (isset($this->field_props[$k]['segment']) && is_int($this->field_props[$k]['segment']))
                    {
                        $segments = explode(' ', $value);
                        $value = empty($segments[$this->field_props[$k]['segment']]) ? '' : $segments[$this->field_props[$k]['segment']];
                    }

                    // Cast
                    if (!empty($this->field_props[$k]['cast']))
                    {
                        switch ($this->field_props[$k]['cast'])
                        {
                            case 'int':
                            case 'integer':
                                $value = (int) $value;
                                break;

                            case 'float':
                                $value = (float) $value;
                                break;

                            case 'string':
                                $value = (string) $value;
                                break;
                        }
                    }

                    // Apply exclusion list
                    if (!empty($this->field_props[$k]['exclude']))
                    {
                        if (Arr::isExpressionFound($this->field_props[$k]['exclude'], $value))
                            $frow['exclude'] = true;
                    }

                    // Convert decimal values
                    $currency = Sanitize::extractCurrency($value, $this->decimal_sep);

                    // Save value or string
                    $frow[$k] = $currency === false ? $this->encode($value) : $currency;

                }

            }

            // Set static values
            foreach ($this->field_props as $k => $props)
            {
                if (is_array($props) && array_key_exists('static_value', $props))
                    $frow[$k] = $props['static_value'];
            }
        }


        return $frow;
    }


    /**
     * Seek the file pointer to an specific line.
     *
     * @param int $line
     * @return bool
     */
    public function seekLine(int $line) : bool
    {
        // Reset file pointer position
        rewind($this->fp);

        // Ignore BOM sequence
        if ($this->has_bom)
            fseek($this->fp, 3);

        $current = 0;

        do
        {
            if ($line === $current)
                return true;

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
    public function tellPosition()
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
     * Apply stream filter.
     *
     * @see http://php.net/manual/en/function.stream-filter-append.php
     * @param $filter
     * @param $params
     * @return resource
     */
    public function applyStreamFilter($filter, $params = null)
    {
        return stream_filter_append($this->fp, $filter, STREAM_FILTER_READ, $params);
    }

    /**
     * Read CSV line.
     *
     * @return array|false|null
     */
    protected function readCSVLine()
    {
        $line = fgetcsv($this->fp, 0, $this->delimiter, $this->enclosure_char,  $this->escape_char);

        return $line;
    }


    /**
     * Encode a text to UTF-8.
     *
     * @param $text
     * @return string
     */
    protected function encode($text) : string
    {
        if ($this->charset === 'UTF-8')
            return $text;

        return mb_convert_encoding($text, 'UTF-8', $this->charset);
    }


}