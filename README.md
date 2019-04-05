# CSVReader

## About

CSVReader is lightweight and fast CSV reader library for PHP 7.x that is suitable for large size files.

CSVReader was developed for business and e-commerce environments where large CSV files with possible corrupted data can be ingested.


## Features

- Easy to use.
- Small footprint (Read files as streams, so low memory is required).
- Read files with different encodings.
- Header column mapping based on string and number references.
- Auto column mapping.
- Support for different decimal separators (European ",", British ".").
- Type casting.
- Currency checking (Avoid to misinterpret corrupted or wrong currency values).
- Detect and ignore empty lines.
- Word segment extraction per column value.
- Word deletion per column. value.
- Column value replacement.
- Value exclusion based on regular expression.


## Notes

- UTF-16 with BOM is not supported


## Usage

### Custom field map (For CSV with header)


        $csv = new \Juanparati\CSVReader\CSVReader(
            'file.csv',     // File path 
            ';',            // Column delimiter
            '"',            // Text enclosure
            'UTF-8',        // Charset
            ',',            // Decimal separator
            '\\'            // Escape character
        );
        
        
        // Define a custom map
        $csv->setMapField([
            'name' => ['column' => 'Firstname'],
            'price' => ['column' => 'Retailprice'],
        ],
        0   // Define where the head line. Default: 0 (first line)
        );
        
        // Extract rows sequentially
        while ($row = $csv->readCSVLine())
        {
            echo 'Name: ' . $row['name'];
            echo 'Price: ' . $row['price'];             
        }


### Custom field map (For CSV without header)

        $csv = new \Juanparati\CSVReader\CSVReader(
            'file.csv',     // File path 
            ';'             // Column delimiter
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
        

### Automatic field map


        $csv = new \Juanparati\CSVReader\CSVReader(
            'file.csv',     // File path 
            ';'             // Column delimiter
        );
               
        // Define a custom map
        $csv->setAutomaticMapField();
        
        // Extract rows sequentially
        while ($row = $csv->readCSVLine())
        {
            echo 'Firstname: ' . $row['Firstname'];
            echo 'Retailprice: ' . $row['Retailprice'];             
        }
        

### Column separators

Separators are set as string or constant representation.

| Separators | Constant |
|------------|----------|
| ;          | \Juanparati\CSVReader\CSVReader::DELIMITER_SEMICOLON |
| ,          | \Juanparati\CSVReader\CSVReader::DELIMITER_COMMA     |
|            | \Juanparati\CSVReader\CSVReader::DELIMITER_PIPE      |
| \t         | \Juanparati\CSVReader\CSVReader::DELIMITER_TAB       |
| ^          | \Juanparati\CSVReader\CSVReader::DELIMITER_CARET     |

It is possible to use all kind of separators so it is not limited to the enumerated ones.


### String enclosures

| Enclosure   | Constant |
|-------------|----------|
| ~           | \Juanparati\CSVReader\CSVReader::ENCLOSURE_TILDES |
| "           | \Juanparati\CSVReader\CSVReader::ENCLOSURE_QUOTES |
| No enclosure| \Juanparati\CSVReader\CSVReader::ENCLOSURE_NONE   |

Enclosure node is used when strings in CSV are not enclosed by any kind of character.


### Decimal separators


| Decimal separator | Constant |
|-------------------|----------|
| .           | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_POINT      |
| ,           | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_COMMA      |
| '           | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_APOSTROPHE |
| ⎖           | \Juanparati\CSVReader\CSVReader::DECIMAL_SEP_APOSTROPHE_9995 |


### Column casting

It is possible to cast columns using the cast attribute.

          // Define a custom map
          $csv->setMapField([                
            'price' => ['column' => 'Retailprice', 'cast' => 'float'],
          ]);


Available casts are:

- int
- integer (alias of int)
- float
- string


### Remove characters from column

Sometimes is required to remove certain characters on a specific column.

          // Define a custom map
          $csv->setMapField([                
            'price' => ['column' => 'Retailprice', 'cast' => 'float', 'remove' => ['EUR', '€'] 
          ]);
          

### Replace characters from column

          // Replace "Mr." by "Señor"
          $csv->setMapField([                
            'name' => ['column' => 'Firstname', 'replace' => ['Mr.' => 'Señor'] 
          ]);
          
          
### Exclude flag

Sometimes is convenient to flag rows according to the column data.

          // Exclude all names that equal to John
          $csv->setMapField([                
            'name' => ['column' => 'Firstname', 'exclude' => ['John'] 
          ]);
          
In this every time that column "name" has the word "John", the virtual column "exclude" will containe the value "true" (boolean).

The exclude parameter accepts regular expressions.


### Apply stream filters

         $csv = new \Juanparati\CSVReader\CSVReader('file.csv');
         $csv->applyStreamFilter('zlib.deflate')
         

### File information

It is possible to get current pointer position in bytes calling to the "tellPosition" method.
In order to obtain the file stat a call to the "info" method will return the file stat (See http://php.net/manual/en/function.fstat.php).

        $csv = new \Juanparati\CSVReader\CSVReader('file.csv');
        echo 'Current byte position ' . $csv->tellPosition() . ' of ' . $csv->info()['size'];
      

## Backers

- [Matchbanker.es](https://matchbanker.es)
