<?php

namespace Juanparati\CSVReader\Tests;

use Juanparati\CSVReader\CSVReader;
use PHPUnit\Framework\TestCase;


abstract class CSVTest extends TestCase
{


    /**
     * Sample file.
     *
     * @var string
     */
    protected $sample = '';


    /**
     * CSVReader instance.
     *
     * @var CSVReader
     */
    protected $instance;


    /**
     * Sample configuration.
     *
     * @var object
     */
    protected $config;


    /**
     * Setup CSVReader instance.
     *
     * @throws \Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $sample_path = __DIR__ . '/Samples/';

        $this->config = json_decode(file_get_contents($sample_path . $this->sample . '.json'), true);

        $this->instance = new CSVReader(
            $sample_path . $this->sample . '.csv',
            $this->config['col_delimiter'],
            $this->config['text_delimiter'],
            $this->config['charset'],
            $this->config['decimal_sep'],
            $this->config['escape_char']
        );

        $this->instance->setMapField($this->config['fields']);
    }


    /**
     * Default tests.
     */
    public function testDefault()
    {
        // Test first line
        if (!empty($this->config['tests']['first']))
        {
            $line = $this->instance->readLine();
            $this->_checkValues($this->config['tests']['first'], $line);
        }

        // Test a line
        if (!empty($this->config['tests']['line']))
        {
            $this->instance->seekLine($this->config['tests']['line']['_num']);
            $line = $this->instance->readLine();
            $this->_checkValues($this->config['tests']['line'], $line);
        }

        // Test last line
        if (!empty($this->config['tests']['last']))
        {
            // Read all the lines until reach the last line.
            // I know that it is not an optimal way to seek the last line,
            // however for test purposes the best is to read all the lines
            // as possible.
            while ($line = $this->instance->readLine())
            {
                $last_line = $line;
            }

            $this->_checkValues($this->config['tests']['last'], $last_line);
        }
    }


    /**
     * Helper that check values according to the field map.
     *
     * @param $map_values
     * @param $row
     */
    protected function _checkValues($map_values, $row)
    {
        foreach ($map_values as $column => $value)
        {
            if ($column === '_num')
                continue;

            $this->assertEquals($value, $row[$column]);
        }
    }


}