<?php
declare(strict_types=1);

namespace Juanparati\CSVReader\Tests\Test;

use Juanparati\CSVReader\CsvReader;
use PHPUnit\Framework\TestCase;

class UTF16Test extends TestCase
{

    public function testUTF16WithBom()
    {
        $reader = (new CsvReader(
            __DIR__ . '/../Samples/sample9.csv',
            ','
        ))->setAutomaticMapField();

        $this->assertEquals('UTF-16LE', $reader->getBOMInfo()['type']);

        $this->assertEquals([
            'ID'          => 1,
            'Name'        => 'Item 1',
            'Value'       => 100,
            'Description' => 'This is the description for item number 1.'
        ], $reader->readLine());
    }
}
