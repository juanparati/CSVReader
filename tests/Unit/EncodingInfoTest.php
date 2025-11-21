<?php

declare(strict_types=1);

namespace Juanparati\CSVReader\Tests\Unit;

use Juanparati\CSVReader\Enums\BomType;
use Juanparati\CSVReader\Helpers\EncodingInfo;
use PHPUnit\Framework\TestCase;

class EncodingInfoTest extends TestCase
{
    public function testHasBomDetectsUtf8Bom(): void
    {
        $stringWithBom = "\xEF\xBB\xBF" . "Hello World";
        $this->assertTrue(EncodingInfo::hasBom($stringWithBom));
    }

    public function testHasBomReturnsFalseWhenNoBom(): void
    {
        $stringWithoutBom = "Hello World";
        $this->assertFalse(EncodingInfo::hasBom($stringWithoutBom));
    }

    public function testStripBomRemovesUtf8Bom(): void
    {
        $stringWithBom = "\xEF\xBB\xBF" . "Hello World";
        $result = EncodingInfo::stripBom($stringWithBom);
        $this->assertEquals("Hello World", $result);
    }

    public function testStripBomRemovesUtf16LeBom(): void
    {
        $stringWithBom = "\xFF\xFE" . "Hello World";
        $result = EncodingInfo::stripBom($stringWithBom);
        $this->assertEquals("Hello World", $result);
    }

    public function testStripBomRemovesUtf16BeBom(): void
    {
        $stringWithBom = "\xFE\xFF" . "Hello World";
        $result = EncodingInfo::stripBom($stringWithBom);
        $this->assertEquals("Hello World", $result);
    }

    public function testStripBomRemovesUtf32LeBom(): void
    {
        $stringWithBom = "\xFF\xFE\x00\x00" . "Hello World";
        $result = EncodingInfo::stripBom($stringWithBom);
        $this->assertEquals("Hello World", $result);
    }

    public function testStripBomRemovesUtf32BeBom(): void
    {
        $stringWithBom = "\x00\x00\xFE\xFF" . "Hello World";
        $result = EncodingInfo::stripBom($stringWithBom);
        $this->assertEquals("Hello World", $result);
    }

    public function testStripBomLeavesStringUnchangedWhenNoBom(): void
    {
        $stringWithoutBom = "Hello World";
        $result = EncodingInfo::stripBom($stringWithoutBom);
        $this->assertEquals("Hello World", $result);
    }

    public function testGetInfoDetectsUtf8Bom(): void
    {
        $bytes = "\xEF\xBB\xBF" . "X";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertEquals(BomType::UTF8, $info['bom']);
        $this->assertEquals(3, $info['bom_length']);
        $this->assertEquals('UTF-8', $info['charset']);
    }

    public function testGetInfoDetectsUtf16LeBom(): void
    {
        $bytes = "\xFF\xFE\x00\x00";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertEquals(BomType::UTF32_LE, $info['bom']);
        $this->assertEquals(4, $info['bom_length']);
        $this->assertEquals('UTF-32LE', $info['charset']);
    }

    public function testGetInfoDetectsUtf16BeBom(): void
    {
        $bytes = "\xFE\xFF\x00\x00";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertEquals(BomType::UTF16_BE, $info['bom']);
        $this->assertEquals(2, $info['bom_length']);
        $this->assertEquals('UTF-16BE', $info['charset']);
    }

    public function testGetInfoDetectsUtf32BeBom(): void
    {
        $bytes = "\x00\x00\xFE\xFF";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertEquals(BomType::UTF32_BE, $info['bom']);
        $this->assertEquals(4, $info['bom_length']);
        $this->assertEquals('UTF-32BE', $info['charset']);
    }

    public function testGetInfoReturnsDefaultWhenNoBom(): void
    {
        $bytes = "TEST";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertNull($info['bom']);
        $this->assertEquals(0, $info['bom_length']);
        $this->assertEquals('UTF-8', $info['charset']);
    }

    public function testGetInfoHandlesShortInput(): void
    {
        $bytes = "AB";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertNull($info['bom']);
        $this->assertEquals(0, $info['bom_length']);
        $this->assertEquals('UTF-8', $info['charset']);
    }

    public function testGetInfoHandlesEmptyInput(): void
    {
        $bytes = "";
        $info = EncodingInfo::getInfo($bytes);

        $this->assertIsArray($info);
        $this->assertNull($info['bom']);
        $this->assertEquals(0, $info['bom_length']);
        $this->assertEquals('UTF-8', $info['charset']);
    }
}
