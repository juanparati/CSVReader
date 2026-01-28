<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Detectors;

use Juanparati\CsvReader\CsvReader;
use Juanparati\CsvReader\Exceptions\CsvFileException;
use Juanparati\CsvReader\Helpers\EncodingInfo;

/**
 * Detects the character encoding (charset) used in a CSV file.
 *
 * Uses BOM detection for files with byte order marks, and heuristics
 * for files without BOMs (UTF-8 validation, ISO-8859 patterns).
 */
class CharsetDetector
{
    use DetectorTrait;

    private string $filePath;
    private ?string $detectedCharset = null;
    private int $confidence = 0;

    /**
     * Create a new charset detector.
     *
     * @param string $filePath Path to the CSV file
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Detect the character encoding.
     *
     * @return string The detected charset
     * @throws CsvFileException If file appears to be binary data
     */
    public function detect(): string
    {
        if (!file_exists($this->filePath)) {
            throw new CsvFileException("Unable to read CSV file: {$this->filePath}");
        }

        // Check if file is binary
        if ($this->isBinaryFile($this->filePath)) {
            throw new CsvFileException("Unable to detect charset: Binary data detected");
        }

        // Try BOM detection first
        $bomCharset = $this->detectFromBom();
        if ($bomCharset !== null) {
            $this->detectedCharset = $bomCharset;
            $this->confidence = 100;
            return $bomCharset;
        }

        // Try heuristics for non-BOM files
        $heuristicCharset = $this->detectFromHeuristics();
        $this->detectedCharset = $heuristicCharset['charset'];
        $this->confidence = $heuristicCharset['confidence'];

        return $this->detectedCharset;
    }

    /**
     * Get the confidence score for the detected charset.
     *
     * @return int Confidence score (0-100)
     */
    public function getConfidence(): int
    {
        return $this->confidence;
    }

    /**
     * Detect charset from BOM (Byte Order Mark).
     *
     * @return string|null The charset if BOM is detected, null otherwise
     */
    private function detectFromBom(): ?string
    {
        $fp = fopen($this->filePath, 'r');
        if ($fp === false) {
            return null;
        }

        try {
            // Read first 4 bytes to check for BOM
            $bytes = fread($fp, 4);
            if ($bytes === false || $bytes === '') {
                return null;
            }

            $encodingInfo = EncodingInfo::getInfo($bytes);

            // If BOM detected, return the charset
            if ($encodingInfo['bom'] !== null) {
                return $encodingInfo['charset'];
            }

            return null;
        } finally {
            fclose($fp);
        }
    }

    /**
     * Detect charset using heuristics for non-BOM files.
     *
     * @return array{charset: string, confidence: int}
     */
    private function detectFromHeuristics(): array
    {
        $fp = fopen($this->filePath, 'r');
        if ($fp === false) {
            return ['charset' => CsvReader::BASE_CHARSET, 'confidence' => 50];
        }

        try {
            // Read a sample of the file (up to 8KB)
            $sample = fread($fp, 8192);
            if ($sample === false || $sample === '') {
                return ['charset' => CsvReader::BASE_CHARSET, 'confidence' => 50];
            }

            // Check if the sample is pure ASCII (no high bytes)
            if ($this->isAscii($sample)) {
                return ['charset' => 'UTF-8', 'confidence' => 95]; // ASCII is valid UTF-8
            }

            // Try to validate as UTF-8
            if ($this->isValidUtf8($sample)) {
                return ['charset' => 'UTF-8', 'confidence' => 90];
            }

            // Check for ISO-8859-1 (Latin-1) patterns
            if ($this->looksLikeIso88591($sample)) {
                return ['charset' => 'ISO-8859-1', 'confidence' => 70];
            }

            // Default to UTF-8 with low confidence
            return ['charset' => CsvReader::BASE_CHARSET, 'confidence' => 50];
        } finally {
            fclose($fp);
        }
    }

    /**
     * Check if a string contains only ASCII characters.
     *
     * @param string $string The string to check
     * @return bool True if string is pure ASCII
     */
    private function isAscii(string $string): bool
    {
        return !preg_match('/[^\x00-\x7F]/', $string);
    }

    /**
     * Validate if a string is valid UTF-8.
     *
     * @param string $string The string to validate
     * @return bool True if string is valid UTF-8
     */
    private function isValidUtf8(string $string): bool
    {
        // Use PHP's mb_check_encoding for UTF-8 validation
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($string, 'UTF-8');
        }

        // Fallback: use preg_match with UTF-8 pattern
        // Valid UTF-8 sequences:
        // - 1 byte:  0xxxxxxx
        // - 2 bytes: 110xxxxx 10xxxxxx
        // - 3 bytes: 1110xxxx 10xxxxxx 10xxxxxx
        // - 4 bytes: 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
        return preg_match('//u', $string) === 1;
    }

    /**
     * Check if a string looks like ISO-8859-1 (Latin-1).
     *
     * @param string $string The string to check
     * @return bool True if string appears to be ISO-8859-1
     */
    private function looksLikeIso88591(string $string): bool
    {
        // ISO-8859-1 has defined characters in the range 0xA0-0xFF
        // If we find bytes in this range that are NOT valid UTF-8, it's likely ISO-8859-1
        $hasHighBytes = preg_match('/[\x80-\xFF]/', $string);

        if (!$hasHighBytes) {
            return false;
        }

        // If it has high bytes but is not valid UTF-8, it's likely ISO-8859-1
        return !$this->isValidUtf8($string);
    }
}
