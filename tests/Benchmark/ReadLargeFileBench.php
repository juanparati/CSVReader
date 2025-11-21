<?php
declare(strict_types=1);

namespace Juanparati\CSVReader\Tests\Benchmark;

use Juanparati\CSVReader\CsvReader;
use Juanparati\CSVReader\FieldMaps\CsvFieldDecimal;
use Juanparati\CSVReader\FieldMaps\CsvFieldInt;
use Juanparati\CSVReader\FieldMaps\CsvFieldString;

class ReadLargeFileBench
{

    /**
     * Read a simple file.
     *
     * @return void
     * @throws \Juanparati\CSVReader\Exceptions\CsvFileException
     */
    public function benchSimpleReadLargeFile()
    {
        $reader = (new \Juanparati\CSVReader\CsvReader(
            file: __DIR__ . '/../tmp/sample.csv',
        ))->setAutomaticMapField();

        foreach ($reader->readGenerator() as $row) {
            if (!isset($row['Index']))
                throw new \Exception(sprintf('Index not found in row: %s', json_encode($row)));
        }
    }


    /**
     * Read with custom map
     *
     * @return void
     * @throws \Juanparati\CSVReader\Exceptions\CsvFileException
     */
    public function benchReadLargeFileWithCustomMap()
    {
        $reader = (new \Juanparati\CSVReader\CsvReader(
            file: __DIR__ . '/../tmp/sample.csv',
        ))->setMapFields([
            'id' => new CsvFieldInt('Index'),
            'price' => new CsvFieldDecimal('Price'),
            'ean' => new CsvFieldString('EAN'),
            'color' => (new CsvFieldString('Color'))->setExclusionRule(['Olive', 'Brown'])
        ]);

        foreach ($reader->readGenerator() as $row) {
            if (!isset($row['Index']))
                throw new \Exception(sprintf('Index not found in row: %s', json_encode($row)));

            if (!is_float($row['price'])) {
                throw new \Exception(sprintf('Price is not a float: %s', json_encode($row)));
            }

            if (in_array($row['color'], ['Olive', 'Brown']) && empty($row['exclude'])) {
                throw new \Exception(sprintf('Color is excluded: %s', json_encode($row)));
            }
        }
    }

}
