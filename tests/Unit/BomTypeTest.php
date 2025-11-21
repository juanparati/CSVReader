<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Tests\Unit;

use Juanparati\CsvReader\Enums\BomType;
use PHPUnit\Framework\TestCase;

class BomTypeTest extends TestCase
{
    public function testDetectUtf8Bom(): void
    {
        $bytes = "\xEF\xBB\xBF" . "content";
        $result = BomType::detectBom($bytes);

        $this->assertNotNull($result);
        $this->assertEquals(BomType::UTF8, $result);
    }

    public function testDetectUtf16LeBom(): void
    {
        $bytes = "\xFF\xFE" . "content";
        $result = BomType::detectBom($bytes);

        $this->assertNotNull($result);
        $this->assertEquals(BomType::UTF16_LE, $result);
    }

    public function testDetectUtf16BeBom(): void
    {
        $bytes = "\xFE\xFF" . "content";
        $result = BomType::detectBom($bytes);

        $this->assertNotNull($result);
        $this->assertEquals(BomType::UTF16_BE, $result);
    }

    public function testDetectUtf32LeBom(): void
    {
        $bytes = "\xFF\xFE\x00\x00" . "content";
        $result = BomType::detectBom($bytes);

        $this->assertNotNull($result);
        $this->assertEquals(BomType::UTF32_LE, $result);
    }

    public function testDetectUtf32BeBom(): void
    {
        $bytes = "\x00\x00\xFE\xFF" . "content";
        $result = BomType::detectBom($bytes);

        $this->assertNotNull($result);
        $this->assertEquals(BomType::UTF32_BE, $result);
    }

    public function testDetectBomReturnsNullWhenNoBom(): void
    {
        $bytes = "No BOM here";
        $result = BomType::detectBom($bytes);

        $this->assertNull($result);
    }

    public function testDetectBomReturnsNullForEmptyString(): void
    {
        $bytes = "";
        $result = BomType::detectBom($bytes);

        $this->assertNull($result);
    }

    public function testUtf8SignatureReturnsCorrectBytes(): void
    {
        $signature = BomType::UTF8->signature();
        $this->assertEquals("\xEF\xBB\xBF", $signature);
    }

    public function testUtf16LeSignatureReturnsCorrectBytes(): void
    {
        $signature = BomType::UTF16_LE->signature();
        $this->assertEquals("\xFF\xFE", $signature);
    }

    public function testUtf16BeSignatureReturnsCorrectBytes(): void
    {
        $signature = BomType::UTF16_BE->signature();
        $this->assertEquals("\xFE\xFF", $signature);
    }

    public function testUtf32LeSignatureReturnsCorrectBytes(): void
    {
        $signature = BomType::UTF32_LE->signature();
        $this->assertEquals("\xFF\xFE\x00\x00", $signature);
    }

    public function testUtf32BeSignatureReturnsCorrectBytes(): void
    {
        $signature = BomType::UTF32_BE->signature();
        $this->assertEquals("\x00\x00\xFE\xFF", $signature);
    }

    public function testUtf8LengthReturnsThreeBytes(): void
    {
        $length = BomType::UTF8->length();
        $this->assertEquals(3, $length);
    }

    public function testUtf16LengthReturnsTwoBytes(): void
    {
        $length = BomType::UTF16_LE->length();
        $this->assertEquals(2, $length);

        $length = BomType::UTF16_BE->length();
        $this->assertEquals(2, $length);
    }

    public function testUtf32LengthReturnsFourBytes(): void
    {
        $length = BomType::UTF32_LE->length();
        $this->assertEquals(4, $length);

        $length = BomType::UTF32_BE->length();
        $this->assertEquals(4, $length);
    }

    public function testBomTypeValues(): void
    {
        $this->assertEquals('UTF-8', BomType::UTF8->value);
        $this->assertEquals('UTF-16LE', BomType::UTF16_LE->value);
        $this->assertEquals('UTF-16BE', BomType::UTF16_BE->value);
        $this->assertEquals('UTF-32LE', BomType::UTF32_LE->value);
        $this->assertEquals('UTF-32BE', BomType::UTF32_BE->value);
    }

    public function testDetectBomPrioritizesUtf32OverUtf16(): void
    {
        // UTF-32 LE starts with same bytes as UTF-16 LE but has 4 bytes
        $bytes = "\xFF\xFE\x00\x00";
        $result = BomType::detectBom($bytes);

        // Should detect as UTF-32 LE, not UTF-16 LE
        $this->assertEquals(BomType::UTF32_LE, $result);
    }
}
