<?php

declare(strict_types=1);

namespace Juanparati\CSVReader\Tests\Unit;

use Juanparati\CSVReader\FieldMaps\CsvFieldAuto;
use Juanparati\CSVReader\FieldMaps\CsvFieldBool;
use Juanparati\CSVReader\FieldMaps\CsvFieldDecimal;
use Juanparati\CSVReader\FieldMaps\CsvFieldInt;
use Juanparati\CSVReader\FieldMaps\CsvFieldString;
use PHPUnit\Framework\TestCase;

class FieldMapTest extends TestCase
{
    // CsvFieldInt Tests
    public function testCsvFieldIntTransformsStringToInt(): void
    {
        $field = new CsvFieldInt(0);
        $result = $field->transform("123");

        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }

    public function testCsvFieldIntTransformsFloatToInt(): void
    {
        $field = new CsvFieldInt(0);
        $result = $field->transform("123.99");

        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }

    public function testCsvFieldIntHandlesNegativeNumbers(): void
    {
        $field = new CsvFieldInt(0);
        $result = $field->transform("-456");

        $this->assertEquals(-456, $result);
    }

    public function testCsvFieldIntHandlesZero(): void
    {
        $field = new CsvFieldInt(0);
        $result = $field->transform("0");

        $this->assertEquals(0, $result);
    }

    // CsvFieldString Tests
    public function testCsvFieldStringTransformsToString(): void
    {
        $field = new CsvFieldString(0);
        $result = $field->transform(123);

        $this->assertIsString($result);
        $this->assertEquals("123", $result);
    }

    public function testCsvFieldStringPreservesString(): void
    {
        $field = new CsvFieldString(0);
        $result = $field->transform("Hello World");

        $this->assertEquals("Hello World", $result);
    }

    // CsvFieldBool Tests
    public function testCsvFieldBoolTransformsTrueValue(): void
    {
        $field = new CsvFieldBool(0);

        $this->assertTrue($field->transform(1));
        $this->assertTrue($field->transform("true"));
        $this->assertTrue($field->transform("on"));
    }

    public function testCsvFieldBoolTransformsFalseValue(): void
    {
        $field = new CsvFieldBool(0);

        $this->assertFalse($field->transform(0));
        $this->assertFalse($field->transform("false"));
        $this->assertFalse($field->transform("off"));
    }

    public function testCsvFieldBoolWithCustomTrueValues(): void
    {
        $field = new CsvFieldBool(0, ['yes', 'Y', '1']);

        $this->assertTrue($field->transform('yes'));
        $this->assertTrue($field->transform('Y'));
        $this->assertTrue($field->transform('1'));
        $this->assertFalse($field->transform('no'));
    }

    public function testCsvFieldBoolIsCaseExact(): void
    {
        $field = new CsvFieldBool(0, ['true']);

        $this->assertTrue($field->transform('true'));
        $this->assertFalse($field->transform('True'));
        $this->assertFalse($field->transform('TRUE'));
    }

    // CsvFieldDecimal Tests
    public function testCsvFieldDecimalTransformsStringToFloat(): void
    {
        $field = new CsvFieldDecimal(0);
        $result = $field->transform("123.45");

        $this->assertIsFloat($result);
        $this->assertEquals(123.45, $result);
    }

    public function testCsvFieldDecimalHandlesCommaDecimalSeparator(): void
    {
        $field = new CsvFieldDecimal(0, CsvFieldDecimal::DECIMAL_SEP_COMMA);
        $result = $field->transform("123,45");

        $this->assertEquals(123.45, $result);
    }

    public function testCsvFieldDecimalHandlesApostropheDecimalSeparator(): void
    {
        $field = new CsvFieldDecimal(0, CsvFieldDecimal::DECIMAL_SEP_APOSTROPHE);
        $result = $field->transform("123'45");

        $this->assertEquals(123.45, $result);
    }

    public function testCsvFieldDecimalHandlesNegativeNumbers(): void
    {
        $field = new CsvFieldDecimal(0);
        $result = $field->transform("-456.78");

        $this->assertEquals(-456.78, $result);
    }

    public function testCsvFieldDecimalHandlesZero(): void
    {
        $field = new CsvFieldDecimal(0);
        $result = $field->transform("0.0");

        $this->assertEquals(0.0, $result);
    }

    public function testCsvFieldDecimalHandlesWholeNumbers(): void
    {
        $field = new CsvFieldDecimal(0);
        $result = $field->transform("100");

        $this->assertEquals(100.0, $result);
    }

    // Replacement Rule Tests
    public function testSetReplaceRuleReplacesValue(): void
    {
        $field = new CsvFieldString(0);
        $field->setReplaceRule('old', 'new');

        $result = $field->transform('This is old text');
        $this->assertEquals('This is new text', $result);
    }

    public function testSetReplaceRuleMultipleReplacements(): void
    {
        $field = new CsvFieldString(0);
        $field->setReplaceRule('foo', 'bar');
        $field->setReplaceRule('baz', 'qux');

        $result = $field->transform('foo and baz');
        $this->assertEquals('bar and qux', $result);
    }

    // Removal Rule Tests
    public function testSetRemoveRuleRemovesValue(): void
    {
        $field = new CsvFieldString(0);
        $field->setRemoveRule('REMOVE');

        $result = $field->transform('KeepREMOVEKeep');
        $this->assertEquals('KeepKeep', $result);
    }

    // Transform Tests
    public function testSetTransformsAppliesCustomTransformation(): void
    {
        $field = new CsvFieldString(0);
        $field->setTransforms([fn ($value) => strtoupper($value)]);

        $result = $field->transform('hello');
        $this->assertEquals('HELLO', $result);
    }

    public function testSetTransformsAppliesMultipleTransformations(): void
    {
        $field = new CsvFieldString(0);
        $field->setTransforms([
            fn ($value) => trim($value),
            fn ($value) => strtoupper($value)
        ]);

        $result = $field->transform('  hello  ');
        $this->assertEquals('HELLO', $result);
    }

    public function testSetTransformsWithStaticValue(): void
    {
        $field = new CsvFieldString(0);
        $field->setTransforms(['STATIC_VALUE']);

        $result = $field->transform('anything');
        $this->assertEquals('STATIC_VALUE', $result);
    }

    // Filter Tests
    public function testShouldBeFilteredWithMatchingValue(): void
    {
        $field = new CsvFieldString(0);
        $field->setFilterRule('FILTER_ME');

        $this->assertTrue($field->shouldBeFiltered('FILTER_ME'));
        $this->assertFalse($field->shouldBeFiltered('KEEP_ME'));
    }

    public function testShouldBeFilteredWithCallable(): void
    {
        $field = new CsvFieldString(0);
        $field->setFilterRule(fn ($value) => $value === 'bad');

        $this->assertTrue($field->shouldBeFiltered('bad'));
        $this->assertFalse($field->shouldBeFiltered('good'));
    }

    // Exclusion Tests
    public function testShouldBeExcludeWithMatchingValue(): void
    {
        $field = new CsvFieldString(0);
        $field->setExclusionRule('EXCLUDE_ME');

        $this->assertTrue($field->shouldBeExclude('EXCLUDE_ME'));
        $this->assertFalse($field->shouldBeExclude('INCLUDE_ME'));
    }

    public function testShouldBeExcludeWithCallable(): void
    {
        $field = new CsvFieldString(0);
        $field->setExclusionRule(fn ($value) => strlen($value) > 10);

        $this->assertTrue($field->shouldBeExclude('This is too long'));
        $this->assertFalse($field->shouldBeExclude('Short'));
    }

    // JSON Serialization Tests
    public function testJsonSerializeIncludesAllProperties(): void
    {
        $field = new CsvFieldString('columnName');
        $field->setReplaceRule('old', 'new');

        $serialized = $field->jsonSerialize();

        $this->assertArrayHasKey('class', $serialized);
        $this->assertArrayHasKey('srcField', $serialized);
        $this->assertArrayHasKey('replacements', $serialized);
        $this->assertArrayHasKey('transforms', $serialized);
        $this->assertArrayHasKey('removals', $serialized);
        $this->assertArrayHasKey('exclusions', $serialized);
        $this->assertArrayHasKey('filters', $serialized);
        $this->assertEquals('columnName', $serialized['srcField']);
    }

    public function testCsvFieldDecimalJsonSerializeIncludesDecimalSeparator(): void
    {
        $field = new CsvFieldDecimal(0, CsvFieldDecimal::DECIMAL_SEP_COMMA);
        $serialized = $field->jsonSerialize();

        $this->assertArrayHasKey('decimalSeparator', $serialized);
        $this->assertEquals(CsvFieldDecimal::DECIMAL_SEP_COMMA, $serialized['decimalSeparator']);
    }

    // Make Method Tests
    public function testMakeCreatesInstanceFromArray(): void
    {
        $settings = [
            'class' => CsvFieldString::class,
            'srcField' => 'test_field',
            'replacements' => ['old' => 'new'],
            'transforms' => [],
            'removals' => [],
            'exclusions' => [],
            'filters' => []
        ];

        $field = CsvFieldString::make($settings);

        $this->assertInstanceOf(CsvFieldString::class, $field);
        $this->assertEquals('test_field', $field->srcField);
    }

    public function testCsvFieldAutoCreation(): void
    {
        $field = new CsvFieldAuto('column_name');

        $this->assertInstanceOf(CsvFieldAuto::class, $field);
        $this->assertEquals('column_name', $field->srcField);
    }

    // Combined transformation tests
    public function testCombinedReplacementAndTransformation(): void
    {
        $field = new CsvFieldString(0);
        $field->setReplaceRule('Mr.', 'Mr');
        $field->setRemoveRule(' ');
        $field->setTransforms([fn ($value) => strtoupper($value)]);

        $result = $field->transform('Mr. John Doe');
        $this->assertEquals('MRJOHNDOE', $result);
    }

    public function testIntFieldWithReplacementRules(): void
    {
        $field = new CsvFieldInt(0);
        $field->setReplaceRule('$', '');
        $field->setReplaceRule(',', '');

        $result = $field->transform('$1,234');
        $this->assertEquals(1234, $result);
    }
}
