![Test passed](https://github.com/juanparati/CSVReader/actions/workflows/test.yml/badge.svg)

# CSVReader

CSVReader is a lightweight and fast CSV reader library for PHP that is suitable for large size files.

CSVReader was developed for business and e-commerce environments where large CSV files with possible corrupted data can be ingested.

## Installation

```sh
composer require juanparati/csvreader
```

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
- Word deletion per column value.
- Column value replacement.
- Value exclusion based on regular expression.
- Full UTF support.
- BOM support.
- Lightweight library without external dependencies (Except PHPUnit for testing).
- CSV format auto-detection (delimiter, enclosure, escape character, charset).


## Usage

### Infer CSV format automatically and open file

```PHP
/**
 * Example CSV file:
 * 
 * name|price
 * John|10.50
 * Mary|20.00
 * 
 */

// Open a CSV file with inferred parameters
$csv = new \Juanparati\CsvReader\CsvReader::open('file.csv');

// Extract rows sequentially
while ($row = $csv->read())
{
    ...         
}
```

### Read CSV file with custom parameters

```PHP
/**
 * Example CSV file:
 * 
 * name;price
 * John;10.50
 * Mary;20.00
 * 
 */

// Create CSVReader instance
$csv = new \Juanparati\CsvReader\CsvReader(
    file: 'file.csv',       // File path
    delimiter: ';',         // Column delimiter (Default semicolon)
    enclosureChar: '"',     // Text enclosure (Default double quotes)
    charset: 'UTF-8',       // Charset (Default auto-detect)
    escapeChar: '\\',       // Escape character (Default backslash)
    excludeField: 'exclude' // Name of the field used for flagging rows (Default: 'exclude')
    streamFilters: []       // Stream filters (Default: [])
);

// Automatic column mapping
$csv->setAutomaticMapField(
    0   // Define where the headline starts. Default: 0 (first line)
);

// Extract rows sequentially
while ($row = $csv->read())
{
    echo 'Name: ' . $row['name'];
    echo 'Price: ' . $row['price'];             
}
```

#### Column separators

Separators are set as string or constant representation.

| Separators | Constant                                             |
|------------|------------------------------------------------------|
| ;          | \Juanparati\CsvReader\CsvReader::DELIMITER_SEMICOLON |
| ,          | \Juanparati\CsvReader\CsvReader::DELIMITER_COMMA     |
| \|         | \Juanparati\CsvReader\CsvReader::DELIMITER_PIPE      |
| \t         | \Juanparati\CsvReader\CsvReader::DELIMITER_TAB       |
| ^          | \Juanparati\CsvReader\CsvReader::DELIMITER_CARET     |
| &          | \Juanparati\CsvReader\CsvReader::DELIMITER_AMPERSAND |

It's possible to use all kinds of separators, so it is not limited to the enumerated ones.


#### String enclosures

| Enclosure    | Constant                                          |
|--------------|---------------------------------------------------|
| ~            | \Juanparati\CsvReader\CsvReader::ENCLOSURE_TILDES |
| "            | \Juanparati\CsvReader\CsvReader::ENCLOSURE_QUOTES |
| No enclosure | \Juanparati\CsvReader\CsvReader::ENCLOSURE_NONE   |

Enclosure none is used when strings in CSV are not enclosed by any kind of character.


### Set custom field maps

```PHP
// Define a custom map
$csv->setMapFields([
    'name' => new \Juanparati\CsvReader\FieldMaps\CsvFieldString(
        'Firstname'
    ),
    'price' => new \Juanparati\CsvReader\FieldMaps\CsvFieldDecimal(
        'Retailprice', \Juanparati\CsvReader\CsvReader::DECIMAL_SEP_COMMA
    )
],
    0   // Define where the headline starts. Default: 0 (first line)
);

// Extract rows sequentially
while ($row = $csv->read())
{
    echo 'Name: ' . $row['name'];
    echo 'Price: ' . $row['price'];             
}
```

## Custom field map (For CSV without header)

```PHP
$csv = new \Juanparati\CsvReader\CsvReader(
    file: 'file.csv',     // File path
    delimiter: ';'        // Column delimiter
);
        
// Define a custom map
$csv->setMapFields([
    'name' => new \Juanparati\CsvReader\FieldMaps\CsvFieldString(
        0   // Column 0
    ),
    'price' => new \Juanparati\CsvReader\FieldMaps\CsvFieldDecimal(
        3,  // Column 3 
        \Juanparati\CsvReader\CsvReader::DECIMAL_SEP_COMMA
    ),
]);

while ($row = $csv->read(0)) {  // Read from line 0 instead of 1 because the header is not present
 ...
}
```

### Field map types

| Type                                            | Description                |
|-------------------------------------------------|----------------------------|
| \Juanparati\CsvReader\FieldMaps\CsvFieldAuto    | Infer automatically column |
| \Juanparati\CsvReader\FieldMaps\CsvFieldBool    | Boolean column             |
| \Juanparati\CsvReader\FieldMaps\CsvFieldDecimal | Currency column            |
| \Juanparati\CsvReader\FieldMaps\CsvFieldInt     | Int column                 |
| \Juanparati\CsvReader\FieldMaps\CsvFieldString  | String column              |


#### CsvFieldDecimal

It's possible to define a decimal separator for currency columns.

| Decimal separator | Constant                                     |
|-------------------|----------------------------------------------|
| .                 | CsvFieldDecimal::DECIMAL_SEP_POINT           |
| ,                 | CsvFieldDecimal::DECIMAL_SEP_COMMA           |
| '                 | CsvFieldDecimal::DECIMAL_SEP_APOSTROPHE      |
| ⎖                 | CsvFieldDecimal::DECIMAL_SEP_APOSTROPHE_9995 |
| _                 | CsvFieldDecimal::DECIMAL_SEP_UNDERSCORE      |
| ٫                 | CsvFieldDecimal::DECIMAL_SEP_ARABIC          |


### Remove characters from columns

Sometimes it is required to remove certain characters on a specific column.

```PHP
// Remove 'EUR' and '€' from column
$csv->setMapField([     
    'price' => (new \Juanparati\CsvReader\FieldMaps\CsvFieldDecimal(
        0   // Column 0
    ))->setRemoveRule(['EUR', '€']),           
]);
```

```PHP
// Remove all prices higher than 100
$csv->setMapField([     
    'price' => (new \Juanparati\CsvReader\FieldMaps\CsvFieldDecimal(
        0   // Column 0
    ))->setRemoveRule(fn($price) => $price > 100),           
]);
```

`setRemoveRule` accepts strings, arrays and callbacks.

### Replace characters from the column

```PHP
// Replace "Mr." by "Señor"
$csv->setMapField([     
    'name' => (new \Juanparati\CsvReader\FieldMaps\CsvFieldString(
        'Firstname'
    ))->setReplaceRule('Mr.', 'Señor')              
]);
```

          
### Exclude flag

Sometimes it's convenient to flag rows according to the column data.

```PHP
// Exclude all names that equal to John
$csv->setMapField([    
    'name' => (new \Juanparati\CsvReader\FieldMaps\CsvFieldString(
        'Firstname'
    ))->setExclusionRule('John')                     
]);
```
          
In this example every time that column "name" has the word "John", the virtual column "exclude" will contain the value "true" (boolean).

`setExclusionRule` accepts strings, arrays and callbacks.


### Apply stream filters

It's possible to apply stream filters to the CSV file. Filter streams are applied before the charset conversion.

```PHP
$filters = [
    new \Juanparati\CsvReader\CsvStreamFilter('zlib.inflate'),
];

$csv = new \Juanparati\CsvReader\CsvReader(
    file: 'file.csv',
    streamFilters: $filter
);
```

### Charset and UTF Support

CSVReader automatically detects and handles UTF-16 and UTF-32 encoded files with and without BOM.

The default charset is UTF-8.

```PHP
// Automatic detection for files that contains BOM (Fallback is UTF-8)
$csv = new \Juanparati\CsvReader\CsvReader('utf16-file.csv');

// The library automatically:
// 1. Detects the BOM (UTF-16LE, UTF-16BE...)
// 2. Applies the appropriate stream filter for conversion
// 3. Processes the file as UTF-8 internally

// You can check the detected BOM information
$encodingInfo = $csv->info()['encoding'];
echo "Has BOM" . ($encodingInfo['bom'] ? 'Yes' : 'No');
echo "Charset: {$encodingInfo['charset'}"; // e.g., "UTF-16LE"
```

Sometimes it's necessary to force a specific charset for files that don't contain a BOM passing the charset as a parameter. Example:

```PHP
// Create CSVReader instance
$csv = new \Juanparati\CsvReader\CsvReader(
    file: 'file.csv',       // File path 
    charset: 'UTF-16',      // Charset (Default auto-detect)
);
```


### File information

It's possible to get the current pointer position in bytes calling to the "tellPosition" method.
To obtain the file stat, a call to the "info" method will return the file stat (See http://php.net/manual/en/function.fstat.php).

```PHP
$csv = new \Juanparati\CsvReader\CsvReader('file.csv');
echo 'Current byte position ' . $csv->tellPosition() . ' of ' . $csv->info()['file']['size'];
```      

## Backers

- [Matchbanker.es](https://matchbanker.es)
