<?php
declare(strict_types=1);

namespace Juanparati\CSVReader\Tests;

use Juanparati\CSVReader\CsvReader;
use PHPUnit\Framework\TestCase;

abstract class CSVTest extends TestCase
{


    /**
     * Sample file.
     */
    protected string $sample = '';


    /**
     * CSVReader instance.
     */
    protected CsvReader $instance;


    /**
     * Sample configuration.
     */
    protected array $config;


    /**
     * Setup CSVReader instance.
     *
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $samplePath = __DIR__ . '/Fixtures/';

        $this->config = json_decode(file_get_contents($samplePath . $this->sample . '.json'), true);

        $this->instance = new CsvReader(
            $samplePath . $this->sample . '.csv',
            $this->config['col_delimiter'],
            $this->config['text_delimiter'],
            $this->config['charset'],
            $this->config['escape_char']
        );

        $this->instance->importFieldMaps( $this->config['fields']);
    }


    /**
     * Default tests.
     */
    public function testDefault(): void
    {
        // Test first line
        if (!empty($this->config['tests']['first'])) {
            $line = $this->instance->readLine();
            $this->checkValues($this->config['tests']['first'], $line);
        }

        // Test a line
        if (!empty($this->config['tests']['line'])) {
            $this->instance->seekLine((int)$this->config['tests']['line']['_num']);
            $line = $this->instance->readLine();
            $this->checkValues($this->config['tests']['line'], $line);
        }

        // Test last line
        if (!empty($this->config['tests']['last'])) {
            // Read all the lines until reach the last line.
            // I know that it is not an optimal way to seek the last line,
            //  however, for test purposes the best is to read all the lines
            // as possible.
            $lastLine = [];
            while ($line = $this->instance->readLine()) {
                $lastLine = $line;
            }

            $this->checkValues($this->config['tests']['last'], $lastLine);
        }
    }


    /**
     * Helper that check values according to the field map.
     *
     * @param array $mapValues
     * @param array $row
     */
    protected function checkValues(array $mapValues, array $row): void
    {
        foreach ($mapValues as $column => $value) {
            if ($column === '_num') {
                continue;
            }

            $this->assertEquals($value, $row[$column]);
        }
    }



}
