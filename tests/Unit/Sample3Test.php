<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Tests\Unit;

use Juanparati\CsvReader\Tests\CSVTest;

class Sample3Test extends CSVTest
{
    protected string $sample = 'sample3';


    /**
     * Default tests.
     */
    public function testDefault(): void
    {
        $this->instance->seekLine($this->config['read_from'] ?? 1);

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
}
