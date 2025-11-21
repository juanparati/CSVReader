<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Tests\Unit;

use Juanparati\CsvReader\CsvReader;
use Juanparati\CsvReader\Exceptions\CsvFileException;
use Juanparati\CsvReader\FieldMaps\CsvFieldAuto;
use Juanparati\CsvReader\FieldMaps\CsvFieldInt;
use Juanparati\CsvReader\FieldMaps\CsvFieldString;
use PHPUnit\Framework\TestCase;

class CsvReaderTest extends TestCase
{
    private string $fixturesPath;
    private string $tempFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = __DIR__ . '/../Fixtures/';
        $this->tempFilePath = sys_get_temp_dir() . '/test_csv_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
    }

    public function testConstructorThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(CsvFileException::class);
        $this->expectExceptionMessage('Unable to read CSV file');

        new CsvReader('/path/to/nonexistent/file.csv');
    }

    public function testConstructorSucceedsWithValidFile(): void
    {
        file_put_contents($this->tempFilePath, "a,b,c\n1,2,3\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $this->assertInstanceOf(CsvReader::class, $reader);
    }

    public function testReadLineWithoutFieldMapping(): void
    {
        file_put_contents($this->tempFilePath, "a,b,c\n1,2,3\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $reader->seekLine(1);
        $line = $reader->readLine();

        $this->assertIsArray($line);
        $this->assertEquals([['1', '2', '3']], $line);
    }

    public function testReadLineReturnsFalseAtEndOfFile(): void
    {
        file_put_contents($this->tempFilePath, "a,b,c\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $reader->seekLine(1);
        $line = $reader->readLine();

        $this->assertFalse($line);
    }

    public function testReadLineReturnsTrueForEmptyLines(): void
    {
        file_put_contents($this->tempFilePath, "a,b,c\n\n4,5,6\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $reader->seekLine(1);
        $line = $reader->readLine();

        $this->assertTrue($line);
    }

    public function testSetAutomaticMapFieldCreatesMapping(): void
    {
        file_put_contents($this->tempFilePath, "name,age,city\nJohn,30,NYC\n");
        $reader = new CsvReader($this->tempFilePath, ',');
        $reader->setAutomaticMapField(0);

        $fieldMaps = $reader->getFieldMaps();

        $this->assertArrayHasKey('name', $fieldMaps);
        $this->assertArrayHasKey('age', $fieldMaps);
        $this->assertArrayHasKey('city', $fieldMaps);
        $this->assertInstanceOf(CsvFieldAuto::class, $fieldMaps['name']);
    }

    public function testSetAutomaticMapFieldIgnoresSingleColumnLine(): void
    {
        file_put_contents($this->tempFilePath, "single\nvalue\n");
        $reader = new CsvReader($this->tempFilePath, ',');
        $result = $reader->setAutomaticMapField(0);

        $this->assertSame($reader, $result);
        $this->assertEmpty($reader->getFieldMaps());
    }

    public function testSetMapFieldsWithStringFieldNames(): void
    {
        file_put_contents($this->tempFilePath, "name,age,city\nJohn,30,NYC\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $fields = [
            'person_name' => new CsvFieldString('name'),
            'person_age' => new CsvFieldInt('age')
        ];

        $reader->setMapFields($fields, 0);
        $fieldMaps = $reader->getFieldMaps();

        $this->assertArrayHasKey('person_name', $fieldMaps);
        $this->assertArrayHasKey('person_age', $fieldMaps);
        $this->assertEquals(0, $fieldMaps['person_name']->srcField);
        $this->assertEquals(1, $fieldMaps['person_age']->srcField);
    }

    public function testSetMapFieldsWithIntegerFieldIndexes(): void
    {
        file_put_contents($this->tempFilePath, "name,age,city\nJohn,30,NYC\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $fields = [
            'col0' => new CsvFieldString(0),
            'col1' => new CsvFieldInt(1)
        ];

        $reader->setMapFields($fields, 0);
        $fieldMaps = $reader->getFieldMaps();

        $this->assertArrayHasKey('col0', $fieldMaps);
        $this->assertArrayHasKey('col1', $fieldMaps);
    }

    public function testSetMapFieldsIgnoresNonMatchingColumns(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $fields = [
            'person_name' => new CsvFieldString('name'),
            'missing' => new CsvFieldString('nonexistent')
        ];

        $reader->setMapFields($fields, 0);
        $fieldMaps = $reader->getFieldMaps();

        $this->assertArrayHasKey('person_name', $fieldMaps);
        $this->assertArrayNotHasKey('missing', $fieldMaps);
    }

    public function testSetMapFieldsThrowsExceptionForInvalidFieldMap(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid field mapping');

        $reader->setMapFields(['invalid' => 'not_a_field_map'], 0);
    }

    public function testReadReturnsAllRows(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\nJane,25\nBob,35\n");
        $reader = new CsvReader($this->tempFilePath, ',');
        $reader->setAutomaticMapField(0);

        $records = $reader->readAll(1);

        $this->assertCount(3, $records);
        $this->assertEquals('John', $records[0]['name']);
        $this->assertEquals('Jane', $records[1]['name']);
        $this->assertEquals('Bob', $records[2]['name']);
    }

    public function testReadGeneratorYieldsRows(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\nJane,25\n");
        $reader = new CsvReader($this->tempFilePath, ',');
        $reader->setAutomaticMapField(0);

        $count = 0;
        foreach ($reader->readMore(1) as $row) {
            $count++;
            $this->assertIsArray($row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('age', $row);
        }

        $this->assertEquals(2, $count);
    }

    public function testReadGeneratorSkipsEmptyLinesByDefault(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\n\nJane,25\n");
        $reader = new CsvReader($this->tempFilePath, ',');
        $reader->setAutomaticMapField(0);

        $count = 0;
        foreach ($reader->readMore(1, true) as $row) {
            $count++;
        }

        $this->assertEquals(2, $count);
    }

    public function testReadGeneratorDoesNotSkipEmptyLinesWhenDisabled(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\n\nJane,25\n");
        $reader = new CsvReader($this->tempFilePath, ',');
        $reader->setAutomaticMapField(0);

        $count = 0;
        $emptyCount = 0;
        foreach ($reader->readMore(1, false) as $row) {
            $count++;
            if ($row === true) {
                $emptyCount++;
            }
        }

        $this->assertEquals(3, $count);
        $this->assertEquals(1, $emptyCount);
    }

    /*
    public function testSeekLinePositionsToCorrectLine(): void
    {
        file_put_contents($this->tempFilePath, "line0\nline1\nline2\nline3\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $this->assertTrue($reader->seekLine(2));

        $line = $reader->readLine();
        $this->assertEquals([['line2']], $line);
    }
*/
    public function testSeekLineReturnsFalseForInvalidLine(): void
    {
        file_put_contents($this->tempFilePath, "line0\nline1\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $this->assertFalse($reader->seekLine(10));
    }

    public function testSeekLineHandlesBomCorrectly(): void
    {
        $utf8Bom = "\xEF\xBB\xBF";
        file_put_contents($this->tempFilePath, $utf8Bom . "name,age\nJohn,30\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $this->assertTrue($reader->seekLine(0));
        $line = $reader->readLine();

        // Should read the header without BOM
        $this->assertEquals([['name', 'age']], $line);
    }

    public function testTellPositionReturnsFilePosition(): void
    {
        file_put_contents($this->tempFilePath, "a,b,c\n1,2,3\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $position = $reader->tellPosition();
        $this->assertIsInt($position);
        $this->assertGreaterThanOrEqual(0, $position);
    }

    public function testInfoReturnsEncodingAndFileStats(): void
    {
        file_put_contents($this->tempFilePath, "a,b,c\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $info = $reader->info();

        $this->assertArrayHasKey('encoding', $info);
        $this->assertArrayHasKey('file', $info);
        $this->assertArrayHasKey('charset', $info['encoding']);
        $this->assertEquals('UTF-8', $info['encoding']['charset']);
    }

    public function testExportFieldMapsReturnsSerializableArray(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $fields = [
            'person_name' => new CsvFieldString('name'),
            'person_age' => new CsvFieldInt('age')
        ];

        $reader->setMapFields($fields, 0);
        $exported = $reader->exportFieldMaps();

        $this->assertIsArray($exported);
        $this->assertArrayHasKey('person_name', $exported);
        $this->assertArrayHasKey('person_age', $exported);
        $this->assertArrayHasKey('class', $exported['person_name']);
        $this->assertArrayHasKey('srcField', $exported['person_name']);
    }

    public function testImportFieldMapsRestoresMapping(): void
    {
        file_put_contents($this->tempFilePath, "name,age\nJohn,30\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $maps = [
            'person_name' => [
                'class' => CsvFieldString::class,
                'srcField' => 0,
                'replacements' => [],
                'transforms' => [],
                'removals' => [],
                'exclusions' => [],
                'filters' => []
            ]
        ];

        $reader->importFieldMaps($maps);
        $fieldMaps = $reader->getFieldMaps();

        $this->assertArrayHasKey('person_name', $fieldMaps);
        $this->assertInstanceOf(CsvFieldString::class, $fieldMaps['person_name']);
    }

    public function testImportFieldMapsThrowsExceptionForInvalidMap(): void
    {
        file_put_contents($this->tempFilePath, "name,age\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid field mapping');

        $reader->importFieldMaps(['bad_field' => ['no_class' => 'value']]);
    }

    /*
    public function testReadLineWithExcludeField(): void
    {
        file_put_contents($this->tempFilePath, "name,status\nJohn,active\nJane,excluded\n");
        $reader = new CsvReader($this->tempFilePath, ',', '"', null, '\\', 'is_excluded');

        $fields = [
            'name' => new CsvFieldString('name'),
            'status' => (new CsvFieldString('status'))->setExclusionRule('excluded')
        ];

        $reader->setMapFields($fields, 0);
        $reader->seekLine(1);

        $row1 = $reader->readLine();
        $this->assertArrayNotHasKey('is_excluded', $row1);

        $row2 = $reader->readLine();
        $this->assertArrayHasKey('is_excluded', $row2);
        $this->assertTrue($row2['is_excluded']);
    }
    */

    /*
    public function testReadLineWithFilteredValue(): void
    {
        file_put_contents($this->tempFilePath, "name,status\nJohn,active\nJane,filtered\nBob,active\n");
        $reader = new CsvReader($this->tempFilePath, ',');

        $fields = [
            'name' => new CsvFieldString('name'),
            'status' => (new CsvFieldString('status'))->setFilterRule('filtered')
        ];

        $reader->setMapFields($fields, 0);
        $reader->seekLine(1);

        $row1 = $reader->readLine();
        $this->assertEquals('John', $row1['name']);

        $row2 = $reader->readLine();
        $this->assertFalse($row2); // Filtered row

        $row3 = $reader->readLine();
        $this->assertEquals('Bob', $row3['name']);
    }
*/
    public function testDifferentDelimiters(): void
    {
        // Semicolon
        file_put_contents($this->tempFilePath, "a;b;c\n1;2;3\n");
        $reader = new CsvReader($this->tempFilePath, CsvReader::DELIMITER_SEMICOLON);
        $reader->seekLine(1);
        $line = $reader->readLine();
        $this->assertEquals([['1', '2', '3']], $line);

        // Pipe
        file_put_contents($this->tempFilePath, "a|b|c\n1|2|3\n");
        $reader = new CsvReader($this->tempFilePath, CsvReader::DELIMITER_PIPE);
        $reader->seekLine(1);
        $line = $reader->readLine();
        $this->assertEquals([['1', '2', '3']], $line);

        // Tab
        file_put_contents($this->tempFilePath, "a\tb\tc\n1\t2\t3\n");
        $reader = new CsvReader($this->tempFilePath, CsvReader::DELIMITER_TAB);
        $reader->seekLine(1);
        $line = $reader->readLine();
        $this->assertEquals([['1', '2', '3']], $line);
    }

    public function testDifferentEnclosures(): void
    {
        file_put_contents($this->tempFilePath, "~a~,~b~,~c~\n~1~,~2~,~3~\n");
        $reader = new CsvReader($this->tempFilePath, ',', CsvReader::ENCLOSURE_TILDES);
        $reader->seekLine(1);
        $line = $reader->readLine();

        $this->assertEquals([['1', '2', '3']], $line);
    }

    public function testEnclosureHandlesEmbeddedDelimiters(): void
    {
        file_put_contents($this->tempFilePath, "\"a,b\",\"c\",\"d\"\n\"1,2\",\"3\",\"4\"\n");
        $reader = new CsvReader($this->tempFilePath, ',', '"');
        $reader->seekLine(1);
        $line = $reader->readLine();

        $this->assertEquals([['1,2', '3', '4']], $line);
    }

    public function testCharsetCanBeEnforced(): void
    {
        $utf8Bom = "\xEF\xBB\xBF";
        file_put_contents($this->tempFilePath, $utf8Bom . "name\nJohn\n");

        // Force a different charset (even though file is UTF-8)
        $reader = new CsvReader($this->tempFilePath, ',', '"', 'ISO-8859-1');
        $info = $reader->info();

        $this->assertEquals('ISO-8859-1', $info['encoding']['charset']);
    }
}
