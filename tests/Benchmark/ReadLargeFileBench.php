<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Tests\Benchmark;

use Juanparati\CsvReader\CsvReader;
use Juanparati\CsvReader\FieldMaps\CsvFieldDecimal;
use Juanparati\CsvReader\FieldMaps\CsvFieldInt;
use Juanparati\CsvReader\FieldMaps\CsvFieldString;

class ReadLargeFileBench
{
    /**
     * Read a simple file.
     *
     * @return void
     * @throws \Juanparati\CsvReader\Exceptions\CsvFileException
     */
    public function benchSimpleReadLargeFile()
    {
        $reader = (new \Juanparati\CsvReader\CsvReader(
            file: __DIR__ . '/../tmp/sample.csv',
        ))->setAutomaticMapField();

        foreach ($reader->read() as $row) {
            if (!isset($row['Index'])) {
                throw new \Exception(sprintf('Index not found in row: %s', json_encode($row)));
            }
        }
    }


    /**
     * Read with custom map
     *
     * @return void
     * @throws \Juanparati\CsvReader\Exceptions\CsvFileException
     */
    public function benchReadLargeFileWithCustomMap()
    {
        $reader = (new \Juanparati\CsvReader\CsvReader(
            file: __DIR__ . '/../tmp/sample.csv',
        ))->setMapFields([
            'id' => new CsvFieldInt('Index'),
            'price' => new CsvFieldDecimal('Price'),
            'ean' => new CsvFieldString('EAN'),
            'color' => (new CsvFieldString('Color'))->setExclusionRule(['Olive', 'Brown'])
        ]);

        foreach ($reader->read() as $row) {
            if (!isset($row['Index'])) {
                throw new \Exception(sprintf('Index not found in row: %s', json_encode($row)));
            }

            if (!is_float($row['price'])) {
                throw new \Exception(sprintf('Price is not a float: %s', json_encode($row)));
            }

            if (in_array($row['color'], ['Olive', 'Brown']) && empty($row['exclude'])) {
                throw new \Exception(sprintf('Color is excluded: %s', json_encode($row)));
            }
        }
    }

}
