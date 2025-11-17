# CSVReader

## About

CSVReader is a lightweight and fast CSV reader library for PHP that is suitable for large size files.

CSVReader was developed for business and e-commerce environments where large CSV files with possible corrupted data can be ingested.


## Features

- Easy to use.
- Small footprint (Read files as streams, so low memory is required).
- Read files with different encodings.
- Header column mapping based on string and number references.
- Auto column mapping.
- Support for different decimal separators (European ",", British ".").
- Type casting.
- Currency checking (Avoid misinterpreting corrupted or wrong currency values).
- Detect and ignore empty lines.
- Word segment extraction per column value.
- Word deletion per column. value.
- Column value replacement.
- Value exclusion based on regular expression.
- Full UTF-8 and UTF-16 support.
- BOM support.
- Lightweight library without external dependencies (Except PHPunit for testing).

## Usage

### Custom field map (For CSV with header)

```PHP
$csv = new \Juanparati\CSVReader\CsvReader(
    file: 'file.csv',     // File path 
    delimiter: ';',            // Column delimiter
    enclosureChar: '"',            // Text enclosure
    charset: 'UTF-8',        // Charset
    decimalSep: ',',            // Decimal separator
    escapeChar: '\\'            // Escape character
);


// Define a custom map
$csv->setMapField([
    'name' => ['column' => 'Firstname'],
    'price' => ['column' => 'Retailprice'],
],
    0   // Define where the headline. Default: 0 (first line)
);

// Extract rows sequentially
while ($row = $csv->readCSVLine())
{
    echo 'Name: ' . $row['name'];
    echo 'Price: ' . $row['price'];             
}
```

### Custom field map (For CSV without header)

```PHP
$csv = new \Juanparati\CSVReader\CsvReader(
    file: 'file.csv',     // File path 
    delimiter: ';'             // Column delimiter
);
        
// Define a custom map
$csv->setMapField([
    'name' => ['column' => 0],
    'price' => ['column' => 3],
]);

// Extract rows sequentially
while ($row = $csv->readLine())
{
    echo 'Name: ' . $row['name'];
    echo 'Price: ' . $row['price'];             
}
```        

### Automatic field map

```PHP
$csv = new \Juanparati\CSVReader\CsvReader(
    file: 'file.csv',     // File path 
    delimiter: ';'             // Column delimiter
);
       
// Define a custom map
$csv->setAutomaticMapField();

// Extract rows sequentially
while ($row = $csv->readCSVLine())
{
    echo 'Firstname: ' . $row['Firstname'];
    echo 'Retailprice: ' . $row['Retailprice'];             
}
```

### Column separators

Separators are set as string or constant representation.

| Separators | Constant                                             |
|------------|------------------------------------------------------|
| ;          | \Juanparati\CSVReader\CSVReader::DELIMITER_SEMICOLON |
| ,          | \Juanparati\CSVReader\CSVReader::DELIMITER_COMMA     |
|            | \Juanparati\CSVReader\CSVReader::DELIMITER_PIPE      |
| \t         | \Juanparati\CSVReader\CSVReader::DELIMITER_TAB       |
| ^          | \Juanparati\CSVReader\CSVReader::DELIMITER_CARET     |

It is possible to use all kind of separators so it is not limited to the enumerated ones.


### String enclosures

| Enclosure    | Constant                                          |
|--------------|---------------------------------------------------|
| ~            | \Juanparati\CSVReader\CSVReader::ENCLOSURE_TILDES |
| "            | \Juanparati\CSVReader\CSVReader::ENCLOSURE_QUOTES |
| No enclosure | \Juanparati\CSVReader\CSVReader::ENCLOSURE_NONE   |

Enclosure node is used when strings in CSV are not enclosed by any kind of character.


### Decimal separators


| Decimal separator | Constant                                                     |
|-------------------|--------------------------------------------------------------|
| .                 | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_POINT           |
| ,                 | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_COMMA           |
| '                 | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_APOSTROPHE      |
| ⎖                 | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_APOSTROPHE_9995 |
| _                 | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_UNDERSCORE      |
| ٫                 | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_ARABIC          |

### Column casting

It is possible to cast columns using the cast attribute.

```PHP
// Define a custom map
$csv->setMapField([                
    'price' => ['column' => 'Retailprice', 'cast' => 'float'],
]);
```

Available casts are:

- int
- integer (alias of int)
- float
- string


### Remove characters from column

Sometimes it is required to remove certain characters on a specific column.

```PHP
// Define a custom map
$csv->setMapField([                
    'price' => ['column' => 'Retailprice', 'cast' => 'float', 'remove' => ['EUR', '€'] 
]);
```

### Replace characters from column

```PHP
// Replace "Mr." by "Señor"
$csv->setMapField([                
    'name' => ['column' => 'Firstname', 'replace' => ['Mr.' => 'Señor'] 
]);
```          
          
### Exclude flag

Sometimes it's convenient to flag rows according to the column data.

```PHP
// Exclude all names that equal to John
$csv->setMapField([                
    'name' => ['column' => 'Firstname', 'exclude' => ['John'] 
]);
```
          
In this every time that column "name" has the word "John", the virtual column "exclude" will contain the value "true" (boolean).

The exclude parameter accepts regular expressions.


### Apply stream filters

```PHP
$filters = [
    new \Juanparati\CSVReader\CsvStreamFilter('zlib.inflate'),
];

$csv = new \Juanparati\CSVReader\CsvReader(
    file: 'file.csv', 
    streamFilters: $filter
);
```

### UTF-16 Support

CSVReader automatically detects and handles UTF-16 encoded files with BOM:

```PHP
// Automatic detection - no special configuration needed!
$csv = new \Juanparati\CSVReader\CsvReader('utf16-file.csv');

// The library automatically:
// 1. Detects the BOM (UTF-16LE or UTF-16BE)
// 2. Applies the appropriate stream filter for conversion
// 3. Processes the file as UTF-8 internally

// You can check the detected BOM information
$bomInfo = $csv->getBOMInfo();
echo "Detected: " . $bomInfo['charset']; // e.g., "UTF-16LE"
```

Supported encodings with BOM auto-detection:
- UTF-8 with BOM
- UTF-16LE (Little Endian) with BOM
- UTF-16BE (Big Endian) with BOM


### File information

It's possible to get the current pointer position in bytes calling to the "tellPosition" method.
To obtain the file stat, a call to the "info" method will return the file stat (See http://php.net/manual/en/function.fstat.php).

```PHP
$csv = new \Juanparati\CSVReader\CsvReader('file.csv');
echo 'Current byte position ' . $csv->tellPosition() . ' of ' . $csv->info()['size'];
```      

## Backers

- [Matchbanker.es](https://matchbanker.es)
