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


