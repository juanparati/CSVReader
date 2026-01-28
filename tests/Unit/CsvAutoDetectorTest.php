<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Tests\Unit;

use Juanparati\CsvReader\CsvAutoDetector;
use Juanparati\CsvReader\CsvReader;
use Juanparati\CsvReader\Detectors\CharsetDetector;
use Juanparati\CsvReader\Detectors\DelimiterDetector;
use Juanparati\CsvReader\Detectors\EnclosureDetector;
use Juanparati\CsvReader\Detectors\EscapeCharDetector;
use Juanparati\CsvReader\Exceptions\CsvFileException;
use PHPUnit\Framework\TestCase;

class CsvAutoDetectorTest extends TestCase
{
    private string $fixturesPath;
    private string $tempFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = __DIR__ . '/../Fixtures/';
        $this->tempFilePath = sys_get_temp_dir() . '/test_csv_autodetect_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
    }

    // ============================================================================
    // CsvAutoDetector Tests
    // ============================================================================

    public function testFullAutoDetectionWithCommaDelimited(): void
    {
        $content = "name,age,city\nJohn,30,NYC\nJane,25,LA\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $format = $detector->detect();

        $this->assertEquals(',', $format['delimiter']);
        $this->assertArrayHasKey('delimiter', $format['confidence']);
        $this->assertGreaterThanOrEqual(70, $format['confidence']['delimiter']);
    }

    public function testFullAutoDetectionWithSemicolonDelimited(): void
    {
        $content = "name;age;city\nJohn;30;NYC\nJane;25;LA\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $format = $detector->detect();

        $this->assertEquals(';', $format['delimiter']);
        $this->assertGreaterThanOrEqual(70, $format['confidence']['delimiter']);
    }

    public function testFullAutoDetectionWithPipeDelimited(): void
    {
        $content = "name|age|city\nJohn|30|NYC\nJane|25|LA\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $format = $detector->detect();

        $this->assertEquals('|', $format['delimiter']);
        $this->assertGreaterThanOrEqual(70, $format['confidence']['delimiter']);
    }

    public function testFullAutoDetectionWithTabDelimited(): void
    {
        $content = "name\tage\tcity\nJohn\t30\tNYC\nJane\t25\tLA\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $format = $detector->detect();

        $this->assertEquals("\t", $format['delimiter']);
        $this->assertGreaterThanOrEqual(70, $format['confidence']['delimiter']);
    }

    public function testDetectWithQuotedFields(): void
    {
        $content = "name,description,price\n\"Product A\",\"A great product\",10.99\n\"Product B\",\"Another product\",20.50\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $format = $detector->detect();

        $this->assertEquals(',', $format['delimiter']);
        $this->assertEquals('"', $format['enclosure']);
    }

    public function testGetConfidenceScores(): void
    {
        $content = "name,age\nJohn,30\nJane,25\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $format = $detector->detect();
        $scores = $detector->getConfidenceScores();

        $this->assertIsArray($scores);
        $this->assertArrayHasKey('delimiter', $scores);
        $this->assertArrayHasKey('enclosure', $scores);
        $this->assertArrayHasKey('escapeChar', $scores);
        $this->assertArrayHasKey('charset', $scores);

        foreach ($scores as $key => $score) {
            $this->assertIsInt($score);
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }
    }

    public function testDetectWithCustomMaxSampleLines(): void
    {
        $content = "a,b,c\n" . str_repeat("1,2,3\n", 100);
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath, maxSampleLines: 10);
        $format = $detector->detect();

        $this->assertEquals(',', $format['delimiter']);
    }

    public function testDetectThrowsExceptionForEmptyFile(): void
    {
        file_put_contents($this->tempFilePath, '');

        $this->expectException(CsvFileException::class);
        $this->expectExceptionMessage('File is empty');

        $detector = new CsvAutoDetector($this->tempFilePath);
        $detector->detect();
    }

    public function testDetectIndividualDelimiter(): void
    {
        $content = "name;age;city\nJohn;30;NYC\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $delimiter = $detector->detectDelimiter();

        $this->assertEquals(';', $delimiter);
    }

    public function testDetectIndividualCharset(): void
    {
        $content = "name,age\nJohn,30\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CsvAutoDetector($this->tempFilePath);
        $charset = $detector->detectCharset();

        $this->assertIsString($charset);
        $this->assertNotEmpty($charset);
    }

    // ============================================================================
    // DelimiterDetector Tests
    // ============================================================================

    public function testDelimiterDetectorWithComma(): void
    {
        $content = "a,b,c\n1,2,3\n4,5,6\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new DelimiterDetector($this->tempFilePath);
        $delimiter = $detector->detect();

        $this->assertEquals(',', $delimiter);
        $this->assertGreaterThanOrEqual(70, $detector->getConfidence());
    }

    public function testDelimiterDetectorWithSemicolon(): void
    {
        $content = "a;b;c\n1;2;3\n4;5;6\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new DelimiterDetector($this->tempFilePath);
        $delimiter = $detector->detect();

        $this->assertEquals(';', $delimiter);
    }

    public function testDelimiterDetectorThrowsOnLowConfidence(): void
    {
        $content = "single column data\nmore data\neven more\n";
        file_put_contents($this->tempFilePath, $content);

        $this->expectException(CsvFileException::class);
        $this->expectExceptionMessageMatches('/Unable to detect delimiter/');

        $detector = new DelimiterDetector($this->tempFilePath, minConfidence: 70);
        $detector->detect();
    }

    public function testDelimiterDetectorGetAllScores(): void
    {
        $content = "a,b,c\n1,2,3\n4,5,6\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new DelimiterDetector($this->tempFilePath);
        $detector->detect();
        $scores = $detector->getAllScores();

        $this->assertIsArray($scores);
        $this->assertNotEmpty($scores);

        foreach ($scores as $delimiter => $score) {
            $this->assertIsString($delimiter);
            $this->assertIsArray($score);
            $this->assertArrayHasKey('score', $score);
            $this->assertArrayHasKey('consistency', $score);
            $this->assertArrayHasKey('frequency', $score);
            $this->assertArrayHasKey('universal', $score);
            $this->assertArrayHasKey('confidence', $score);
        }
    }

    // ============================================================================
    // EnclosureDetector Tests
    // ============================================================================

    public function testEnclosureDetectorWithQuotes(): void
    {
        $content = "\"name\",\"age\"\n\"John\",\"30\"\n\"Jane\",\"25\"\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new EnclosureDetector($this->tempFilePath, ',');
        $enclosure = $detector->detect();

        $this->assertEquals('"', $enclosure);
    }

    public function testEnclosureDetectorWithNoEnclosure(): void
    {
        $content = "name,age\nJohn,30\nJane,25\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new EnclosureDetector($this->tempFilePath, ',');
        $enclosure = $detector->detect();

        // Should detect no enclosure or have low confidence
        $this->assertTrue(
            $enclosure === CsvReader::ENCLOSURE_NONE || $detector->getConfidence() < 50
        );
    }

    public function testEnclosureDetectorWithMixedQuotes(): void
    {
        $content = "\"name\",age,\"city\"\n\"John Doe\",30,\"New York\"\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new EnclosureDetector($this->tempFilePath, ',');
        $enclosure = $detector->detect();

        $this->assertEquals('"', $enclosure);
    }

    // ============================================================================
    // EscapeCharDetector Tests
    // ============================================================================

    public function testEscapeCharDetectorWithBackslash(): void
    {
        $content = "name,description\n\"Product\",\"A \\\"great\\\" product\"\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new EscapeCharDetector($this->tempFilePath, '"');
        $escapeChar = $detector->detect();

        $this->assertEquals('\\', $escapeChar);
    }

    public function testEscapeCharDetectorWithNoEnclosure(): void
    {
        $content = "name,age\nJohn,30\nJane,25\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new EscapeCharDetector($this->tempFilePath, CsvReader::ENCLOSURE_NONE);
        $escapeChar = $detector->detect();

        // Should default to backslash when no enclosure
        $this->assertEquals('\\', $escapeChar);
    }

    public function testEscapeCharDetectorDefaultsToBackslash(): void
    {
        $content = "name,description\n\"Product\",\"A simple product\"\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new EscapeCharDetector($this->tempFilePath, '"');
        $escapeChar = $detector->detect();

        // Should default to backslash when no clear pattern
        $this->assertEquals('\\', $escapeChar);
    }

    // ============================================================================
    // CharsetDetector Tests
    // ============================================================================

    public function testCharsetDetectorWithUtf8(): void
    {
        $content = "name,city\nJohn,New York\nJuan,España\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CharsetDetector($this->tempFilePath);
        $charset = $detector->detect();

        $this->assertEquals('UTF-8', $charset);
        $this->assertGreaterThan(0, $detector->getConfidence());
    }

    public function testCharsetDetectorWithAscii(): void
    {
        $content = "name,age\nJohn,30\nJane,25\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CharsetDetector($this->tempFilePath);
        $charset = $detector->detect();

        // ASCII is valid UTF-8
        $this->assertEquals('UTF-8', $charset);
    }

    public function testCharsetDetectorWithUtf8Bom(): void
    {
        $content = "\xEF\xBB\xBFname,age\nJohn,30\n";
        file_put_contents($this->tempFilePath, $content);

        $detector = new CharsetDetector($this->tempFilePath);
        $charset = $detector->detect();

        $this->assertEquals('UTF-8', $charset);
        $this->assertEquals(100, $detector->getConfidence());
    }

    // ============================================================================
    // Integration Tests with Fixtures
    // ============================================================================

    public function testAutoDetectSample2(): void
    {
        $samplePath = $this->fixturesPath . 'sample2.csv';

        if (!file_exists($samplePath)) {
            $this->markTestSkipped('sample2.csv fixture not found');
        }

        $detector = new CsvAutoDetector($samplePath);
        $format = $detector->detect();

        // sample2 is pipe-delimited, UTF-8, no enclosure
        $this->assertEquals('|', $format['delimiter']);
        $this->assertEquals('UTF-8', $format['charset']);
    }

    public function testAutoDetectSample3(): void
    {
        $samplePath = $this->fixturesPath . 'sample3.csv';

        if (!file_exists($samplePath)) {
            $this->markTestSkipped('sample3.csv fixture not found');
        }

        // sample3 has sparse data, so use lower confidence threshold
        $detector = new CsvAutoDetector($samplePath, minConfidence: 60);
        $format = $detector->detect();

        // sample3 is tab-delimited, UTF-8, no enclosure (sparse data)
        $this->assertEquals("\t", $format['delimiter']);
        $this->assertEquals('UTF-8', $format['charset']);
        // No enclosure in this file
        $this->assertEquals(CsvReader::ENCLOSURE_NONE, $format['enclosure']);
    }


    public function testCsvReaderOpenFactory(): void
    {
        $content = "\"name\"|\"age\"\n\"John\"|30\n\"Jane\"|25\n";
        file_put_contents($this->tempFilePath, $content);

        $reader = CsvReader::open($this->tempFilePath)
            ->setAutomaticMapField(0);

        $rows = iterator_to_array($reader->read(1));

        $this->assertCount(2, $rows);
        $this->assertEquals('John', $rows[0]['name']);
        $this->assertEquals('30', $rows[0]['age']);
    }
}
